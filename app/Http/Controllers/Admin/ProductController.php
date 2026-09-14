<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name'])
            ->withCount('variants')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.products.index', ['products' => $products]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'product' => new Product(['status' => 'draft', 'base_price' => 0, 'is_featured' => false]),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
            'variants' => collect(),
            'images' => collect(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $variantsData = $data['variants'] ?? [];
        $images = $request->file('images') ?? [];

        $product = DB::transaction(function () use ($data, $variantsData, $images) {
            $product = Product::query()->create([
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'],
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'],
                'status' => $data['status'],
                'is_featured' => (bool) ($data['is_featured'] ?? false),
            ]);

            foreach ($variantsData as $variantData) {
                ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'size' => $variantData['size'],
                    'color' => $variantData['color'],
                    'sku' => $variantData['sku'],
                    'price' => $variantData['price'] ?? null,
                    'stock_quantity' => (int) $variantData['stock_quantity'],
                    'low_stock_threshold' => (int) $variantData['low_stock_threshold'],
                    'is_active' => (bool) ($variantData['is_active'] ?? true),
                ]);
            }

            $this->storeProductImages($product, $images);

            return $product;
        });

        return redirect()->route('admin.products.show', $product)
            ->with('status', "Sản phẩm \"{$product->name}\" đã được tạo.");
    }

    public function show(Product $product): View
    {
        $product->load(['category:id,name', 'brand:id,name', 'variants', 'images']);

        return view('admin.products.show', ['product' => $product]);
    }

    public function edit(Product $product): View
    {
        $product->load('variants', 'images');

        return view('admin.products.edit', [
            'product' => $product,
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
            'variants' => $product->variants,
            'images' => $product->images,
        ]);
    }

    public function update(StoreProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $variantsData = $data['variants'] ?? [];
        $images = $request->file('images') ?? [];

        DB::transaction(function () use ($product, $data, $variantsData, $images) {
            $product->update([
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'],
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'],
                'status' => $data['status'],
                'is_featured' => (bool) ($data['is_featured'] ?? false),
            ]);

            $this->syncProductVariants($product, $variantsData);
            $this->storeProductImages($product, $images);
        });

        return redirect()->route('admin.products.show', $product)
            ->with('status', "Sản phẩm \"{$product->name}\" đã được cập nhật.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        abort_unless(auth()->user()->can('products.manage'), 403);

        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Sản phẩm đã được xóa.');
    }

    /**
     * Persist uploaded image files for a product and link them to the product.
     */
    private function storeProductImages(Product $product, array $images): void
    {
        if (empty($images)) {
            return;
        }

        $existingImages = $product->images()->get()->keyBy('id');
        $sortOrder = $existingImages->count();

        foreach ($images as $image) {
            if (! $image || ! $image->isValid()) {
                continue;
            }

            $path = $image->store('products', 's3');

            ProductImage::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => null,
                'path' => $path,
                'alt_text' => $product->name,
                'sort_order' => $sortOrder++,
                'is_primary' => false,
            ]);
        }
    }

    /**
     * Sync the product's variants: update existing, create new.
     * Variants with a `id` field are treated as existing (matched by id);
     * rows without an id are created.
     */
    private function syncProductVariants(Product $product, array $variantsData): void
    {
        $existing = $product->variants()->get()->keyBy(fn (ProductVariant $v) => (int) $v->id);
        $seenVariantIds = [];

        foreach ($variantsData as $key => $variantData) {
            // The array key is the variant's id for existing variants,
            // or a sequential index for new variants.
            $variantId = is_numeric($key) ? (int) $key : null;

            $size = $variantData['size'];
            $color = $variantData['color'];
            $sku = $variantData['sku'];
            $price = $variantData['price'] ?? null;
            $stock = (int) $variantData['stock_quantity'];
            $threshold = (int) $variantData['low_stock_threshold'];
            $active = (bool) ($variantData['is_active'] ?? true);

            if ($variantId !== null && $existing->has($variantId)) {
                $existing[$variantId]->update([
                    'size' => $size,
                    'color' => $color,
                    'sku' => $sku,
                    'price' => $price,
                    'stock_quantity' => $stock,
                    'low_stock_threshold' => $threshold,
                    'is_active' => $active,
                ]);
            } else {
                ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'size' => $size,
                    'color' => $color,
                    'sku' => $sku,
                    'price' => $price,
                    'stock_quantity' => $stock,
                    'low_stock_threshold' => $threshold,
                    'is_active' => $active,
                ]);
            }
        }
    }
}
