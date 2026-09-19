<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\AdminPagination;
use App\Support\AdminSorting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $sorting = new AdminSorting($request, [
            'name' => 'name', 'variants_count' => 'variants_count', 'status' => 'status', 'is_featured' => 'is_featured',
            'category' => Category::query()->select('name')->whereColumn('categories.id', 'products.category_id'),
            'brand' => Brand::query()->select('name')->whereColumn('brands.id', 'products.brand_id'),
        ]);
        $products = $sorting->apply(Product::query()
            ->with(['category:id,name', 'brand:id,name', 'images'])
            ->withCount('variants')
            ->orderByDesc('created_at')
            ->orderByDesc('id'))
            ->paginate(AdminPagination::perPage($request))->withQueryString();

        return view('admin.products.index', ['products' => $products, 'sorting' => $sorting]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'product' => new Product(['status' => 'active', 'base_price' => 0, 'is_featured' => false]),
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

            $variantIds = [];
            foreach ($variantsData as $key => $variantData) {
                $variant = ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'size' => $variantData['size'],
                    'color' => $variantData['color'],
                    'sku' => $variantData['sku'],
                    'price' => $variantData['price'] ?? null,
                    'stock_quantity' => (int) $variantData['stock_quantity'],
                    'low_stock_threshold' => (int) $variantData['low_stock_threshold'],
                    'is_active' => (bool) ($variantData['is_active'] ?? true),
                ]);
                $variantIds[$key] = $variant->id;
            }

            $this->syncProductImages($product, $images, $variantsData, $variantIds);

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

        $removedPaths = DB::transaction(function () use ($product, $data, $variantsData, $images) {
            $product->update([
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'],
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'],
                'status' => $data['status'],
                'is_featured' => (bool) ($data['is_featured'] ?? $product->is_featured),
            ]);

            $variantIds = $this->syncProductVariants($product, $variantsData);
            $removedPaths = $this->removeProductImages($product, $data['removed_images'] ?? []);
            $this->syncProductImages($product, $images, $variantsData, $variantIds);

            return $removedPaths;
        });

        // Some demo photos are shared by multiple products; keep files still in use.
        $unusedPaths = array_diff($removedPaths, ProductImage::query()->whereIn('path', $removedPaths)->pluck('path')->all());
        if ($unusedPaths) {
            Storage::disk(config('filesystems.image_disk'))->delete(array_values($unusedPaths));
        }

        return redirect()->route('admin.products.show', $product)
            ->with('status', "Sản phẩm \"{$product->name}\" đã được cập nhật.");
    }

    public function updateFeatured(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['is_featured' => ['required', 'boolean']]);
        $product->update($data + ['updated_by' => $request->user()->id]);

        if ($request->expectsJson()) {
            return response()->json(['is_featured' => $product->is_featured]);
        }

        return back()->with('status', $product->is_featured
            ? 'Đã đánh dấu sản phẩm nổi bật.'
            : 'Đã bỏ đánh dấu sản phẩm nổi bật.');
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
    private function storeProductImages(Product $product, array $images, ?int $variantId = null): void
    {
        if (empty($images)) {
            return;
        }

        $existingImages = $product->images()->get()->keyBy('id');
        $sortOrder = $existingImages->count();
        $hasPrimary = $existingImages->contains('is_primary', true);

        foreach ($images as $image) {
            if (! $image || ! $image->isValid()) {
                continue;
            }

            $path = $image->store('products', config('filesystems.image_disk'));

            ProductImage::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'path' => $path,
                'alt_text' => $product->name,
                'sort_order' => $sortOrder++,
                'is_primary' => ! $hasPrimary,
            ]);

            $hasPrimary = true;
        }
    }

    private function syncProductImages(Product $product, array $images, array $variants, array $variantIds): void
    {
        // Removing a variant preserves its images as shared product images.
        $product->images()->whereNotNull('product_variant_id')->whereNotIn('product_variant_id', $variantIds)
            ->update(['product_variant_id' => null]);
        $this->storeProductImages($product, $images);
        foreach ($variants as $key => $variant) {
            $this->storeProductImages($product, $variant['images'] ?? [], $variantIds[$key]);
        }
    }

    private function removeProductImages(Product $product, array $imageIds): array
    {
        $images = $product->images()->whereIn('id', $imageIds)->get();
        $product->images()->whereIn('id', $images->modelKeys())->delete();
        if (! $product->images()->where('is_primary', true)->exists()) {
            $product->images()->orderBy('sort_order')->orderBy('id')->first()?->update(['is_primary' => true]);
        }

        return $images->pluck('path')->all();
    }

    /**
     * Sync the product's variants: update existing, create new, and
     * soft-delete any existing variant no longer present in $variantsData
     * (the admin form's "×" button just omits that row's inputs on submit —
     * the form represents the full desired set of variants).
     *
     * Variants with a numeric key matching a real existing id are treated as
     * updates; everything else (including the "new-N" keys the "+ Thêm biến
     * thể" button generates) is created.
     */
    private function syncProductVariants(Product $product, array $variantsData): array
    {
        $existing = $product->variants()->get()->keyBy(fn (ProductVariant $v) => (int) $v->id);
        $keptVariantIds = [];

        foreach ($variantsData as $key => $variantData) {
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
                $keptVariantIds[$key] = $variantId;
            } else {
                $created = ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'size' => $size,
                    'color' => $color,
                    'sku' => $sku,
                    'price' => $price,
                    'stock_quantity' => $stock,
                    'low_stock_threshold' => $threshold,
                    'is_active' => $active,
                ]);
                $keptVariantIds[$key] = $created->id;
            }
        }

        $existing->keys()->diff($keptVariantIds)->each(
            fn (int $removedId) => $existing[$removedId]->delete()
        );

        return $keptVariantIds;
    }
}
