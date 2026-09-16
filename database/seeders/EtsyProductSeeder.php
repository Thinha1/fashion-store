<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EtsyProductSeeder extends Seeder
{
    private const DATA_FILE = 'seeders/data/etsy-products.json';

    private const STORAGE_PREFIX = 'etsy';

    private function disk()
    {
        return Storage::disk(config('filesystems.image_disk'));
    }

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $definitions = $this->loadDefinitions();

        DB::transaction(function () use ($admin, $definitions): void {
            foreach ($definitions as $definition) {
                $this->seedProduct($admin, $definition);
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadDefinitions(): array
    {
        $path = database_path(self::DATA_FILE);

        if (! is_file($path)) {
            throw new RuntimeException("Không tìm thấy file: {$path}");
        }

        $definitions = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($definitions)) {
            throw new RuntimeException('Dữ liệu Etsy products không hợp lệ.');
        }

        return $definitions;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedProduct(User $admin, array $definition): void
    {
        $brand = $this->seedBrand($admin, $definition['brand']);
        $category = $this->resolveCategory($definition['category']);
        $product = $this->seedProductRow($admin, $definition, $brand, $category);
        $variantId = $this->seedVariant($admin, $product, $definition);

        $this->seedImages($admin, $product, $variantId, $definition);
    }

    /**
     * @param  array<string, mixed>  $brandData
     */
    private function seedBrand(User $admin, array $brandData): Brand
    {
        $logoPath = $this->downloadAndStore($brandData['logo_url'], 'brands', $brandData['slug'].'.jpg');

        return Brand::query()->updateOrCreate(
            ['slug' => $brandData['slug']],
            [
                'name' => $brandData['name'],
                'description' => 'Thương hiệu từ Etsy: '.$brandData['name'].'.',
                'logo_path' => $logoPath,
                'country' => $brandData['country'] ?? null,
                'is_active' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );
    }

    private function resolveCategory(string $slug): Category
    {
        $category = Category::query()->where('slug', $slug)->first();

        if ($category) {
            return $category;
        }

        return $this->createCategoryChain($slug);
    }

    private function createCategoryChain(string $slug): Category
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $levels = [
            ['slug' => 'unisex', 'name' => 'Unisex', 'parent_key' => null],
            ['slug' => 'unisex-ao', 'name' => 'Áo unisex', 'parent_key' => 'unisex'],
            ['slug' => 'ao-thun-unisex', 'name' => 'Áo thun', 'parent_key' => 'unisex-ao'],
        ];

        $chain = [];

        foreach ($levels as $i => $level) {
            $parentId = $level['parent_key'] ? $chain[$level['parent_key']]->id : null;

            $chain[$level['slug']] = Category::query()->updateOrCreate(
                ['slug' => $level['slug']],
                [
                    'parent_id' => $parentId,
                    'name' => $level['name'],
                    'description' => $level['name'],
                    'is_active' => true,
                    'sort_order' => ($i + 1) * 10,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        return $chain[$slug] ?? throw new RuntimeException("Không thể tạo chuỗi danh mục cho '{$slug}'.");
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedProductRow(User $admin, array $definition, Brand $brand, Category $category): Product
    {
        $product = Product::withTrashed()->firstOrNew(['slug' => $definition['slug']]);
        $product->fill([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => $definition['name'],
            'description' => $definition['description'],
            'base_price' => $definition['base_price'],
            'status' => 'active',
            'is_featured' => false,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $product->save();
        $product->restore();

        return $product;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedVariant(User $admin, Product $product, array $definition): ?int
    {
        $firstVariant = $definition['variants'][0];

        $variant = ProductVariant::withTrashed()->firstOrNew(['sku' => $firstVariant['sku']]);
        $variant->fill([
            'product_id' => $product->id,
            'size' => $firstVariant['size'],
            'color' => $firstVariant['color'],
            'price' => $firstVariant['price'],
            'stock_quantity' => $definition['stock_quantity'],
            'low_stock_threshold' => 5,
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $variant->save();
        $variant->restore();

        return $variant->id;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedImages(User $admin, Product $product, ?int $variantId, array $definition): void
    {
        $product->images()->update(['is_primary' => false]);

        foreach ($definition['images'] as $index => $imageData) {
            $filename = 'listing-'.$definition['listing_id'].'-'.($index + 1).'.jpg';
            $storedPath = $this->downloadAndStore($imageData['url'], 'products', $filename);

            ProductImage::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'path' => $storedPath,
                ],
                [
                    'product_variant_id' => $variantId,
                    'alt_text' => $imageData['alt'],
                    'sort_order' => $index,
                    'is_primary' => $index === 0,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }
    }

    private function downloadAndStore(string $url, string $subDir, string $filename): string
    {
        $targetPath = self::STORAGE_PREFIX.'/'.$subDir.'/'.$filename;

        if ($this->disk()->exists($targetPath)) {
            return $targetPath;
        }

        $contents = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36\r\n",
                'timeout' => 15,
            ],
        ]));

        if ($contents === false) {
            throw new RuntimeException("Không thể tải ảnh từ {$url}");
        }

        if (! $this->disk()->put($targetPath, $contents, ['visibility' => 'public'])) {
            throw new RuntimeException("Không thể lưu ảnh {$filename} lên disk.");
        }

        return $targetPath;
    }
}
