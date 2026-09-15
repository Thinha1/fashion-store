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

class CatalogDemoSeeder extends Seeder
{
    private const ASSET_DIRECTORY = 'assets/catalog';

    private const STORAGE_DIRECTORY = 'demo/catalog';

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $imagePaths = $this->publishImages();
        $logoPaths = $this->publishBrandLogos();

        DB::transaction(function () use ($admin, $imagePaths, $logoPaths): void {
            $brands = $this->seedBrands($admin, $logoPaths);
            $categories = $this->seedCategories($admin, $imagePaths);
            $this->seedProducts($admin, $brands, $categories, $imagePaths);
        });
    }

    /**
     * @param  array<string, string>  $logoPaths
     * @return array<string, Brand>
     */
    private function seedBrands(User $admin, array $logoPaths): array
    {
        $definitions = [
            'lang-studio' => [
                'name' => 'Lặng Studio',
                'description' => 'Thương hiệu giả lập theo đuổi phom dáng tối giản, bảng màu trung tính và chất liệu dễ mặc hằng ngày.',
                'country' => 'Việt Nam',
            ],
            'moc-daily' => [
                'name' => 'Mộc Daily',
                'description' => 'Dòng trang phục giả lập tập trung vào cotton, linen và cảm giác thoải mái cho nhịp sống đô thị.',
                'country' => 'Việt Nam',
            ],
            'dai-nang' => [
                'name' => 'Dải Nắng',
                'description' => 'Thương hiệu giả lập với các thiết kế mềm mại, tông màu ấm và khả năng phối lớp linh hoạt.',
                'country' => 'Việt Nam',
            ],
            'northline' => [
                'name' => 'Northline',
                'description' => 'Nhãn hàng giả lập mang tinh thần smart casual, kết hợp đường nét gọn gàng với chất liệu bền.',
                'country' => 'Nhật Bản',
            ],
        ];

        $brands = [];

        foreach ($definitions as $slug => $definition) {
            $brands[$slug] = Brand::query()->updateOrCreate(
                ['slug' => $slug],
                $definition + [
                    'logo_path' => $logoPaths[$slug],
                    'is_active' => true,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        return $brands;
    }

    /**
     * @param  array<string, string>  $imagePaths
     * @return array<string, Category>
     */
    private function seedCategories(User $admin, array $imagePaths): array
    {
        $definitions = [
            ['slug' => 'nam', 'name' => 'Nam', 'parent' => null, 'image' => 'bo-suu-tap-05.jpg'],
            ['slug' => 'nu', 'name' => 'Nữ', 'parent' => null, 'image' => 'bo-suu-tap-03.jpg'],
            ['slug' => 'unisex', 'name' => 'Unisex', 'parent' => null, 'image' => 'bo-suu-tap-01.jpg'],

            ['slug' => 'nam-ao', 'name' => 'Áo nam', 'parent' => 'nam', 'image' => 'ao-so-mi.jpg'],
            ['slug' => 'nam-quan', 'name' => 'Quần nam', 'parent' => 'nam', 'image' => 'bo-suu-tap-04.jpg'],
            ['slug' => 'nu-ao', 'name' => 'Áo nữ', 'parent' => 'nu', 'image' => 'bo-suu-tap-03.jpg'],
            ['slug' => 'nu-vay', 'name' => 'Váy nữ', 'parent' => 'nu', 'image' => 'bo-suu-tap-01.jpg'],
            ['slug' => 'unisex-ao', 'name' => 'Áo unisex', 'parent' => 'unisex', 'image' => 'ao-thun.jpg'],

            ['slug' => 'ao-thun-nam', 'name' => 'Áo thun', 'parent' => 'nam-ao', 'image' => 'ao-thun.jpg'],
            ['slug' => 'ao-so-mi-nam', 'name' => 'Áo sơ mi', 'parent' => 'nam-ao', 'image' => 'bo-suu-tap-02.jpg'],
            ['slug' => 'ao-len-nam', 'name' => 'Áo len', 'parent' => 'nam-ao', 'image' => 'ao-len.jpg'],
            ['slug' => 'quan-dai-nam', 'name' => 'Quần dài', 'parent' => 'nam-quan', 'image' => 'bo-suu-tap-04.jpg'],
            ['slug' => 'ao-thun-nu', 'name' => 'Áo thun', 'parent' => 'nu-ao', 'image' => 'bo-suu-tap-01.jpg'],
            ['slug' => 'ao-so-mi-nu', 'name' => 'Áo sơ mi', 'parent' => 'nu-ao', 'image' => 'bo-suu-tap-04.jpg'],
            ['slug' => 'ao-len-nu', 'name' => 'Áo len & cardigan', 'parent' => 'nu-ao', 'image' => 'bo-suu-tap-03.jpg'],
            ['slug' => 'vay-lien-nu', 'name' => 'Váy liền', 'parent' => 'nu-vay', 'image' => 'bo-suu-tap-01.jpg'],
            ['slug' => 'ao-thun-unisex', 'name' => 'Áo thun', 'parent' => 'unisex-ao', 'image' => 'ao-thun.jpg'],
            ['slug' => 'ao-len-unisex', 'name' => 'Áo len', 'parent' => 'unisex-ao', 'image' => 'ao-len.jpg'],
        ];

        $categories = [];
        $sortOrders = [];

        foreach ($definitions as $definition) {
            $parent = $definition['parent'] ? $categories[$definition['parent']] : null;
            $parentKey = $definition['parent'] ?? 'root';
            $sortOrders[$parentKey] = ($sortOrders[$parentKey] ?? 0) + 10;

            $categories[$definition['slug']] = Category::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'parent_id' => $parent?->id,
                    'name' => $definition['name'],
                    'description' => 'Danh mục demo dành cho '.$definition['name'].'.',
                    'image_path' => $imagePaths[$definition['image']],
                    'is_active' => true,
                    'sort_order' => $sortOrders[$parentKey],
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        return $categories;
    }

    /**
     * @param  array<string, Brand>  $brands
     * @param  array<string, Category>  $categories
     * @param  array<string, string>  $imagePaths
     */
    private function seedProducts(User $admin, array $brands, array $categories, array $imagePaths): void
    {
        $definitions = [
            [
                'slug' => 'ao-thun-essential-trang',
                'name' => 'Áo thun Essential trắng',
                'category' => 'ao-thun-unisex',
                'brand' => 'lang-studio',
                'description' => 'Áo thun cổ tròn phom regular, bề mặt cotton mềm và dễ phối lớp. Một lựa chọn cơ bản cho tủ đồ hằng ngày.',
                'price' => 329000,
                'image' => 'ao-thun.jpg',
                'sku' => 'LST-TEE-ESS',
                'colors' => ['Trắng' => 'WHT', 'Đen' => 'BLK'],
            ],
            [
                'slug' => 'ao-len-texture',
                'name' => 'Áo len Texture',
                'category' => 'ao-len-unisex',
                'brand' => 'northline',
                'description' => 'Áo len dệt nổi với độ dày vừa phải, giữ ấm mà vẫn thoáng khi phối nhiều lớp. Bảng màu trung tính phù hợp cả nam và nữ.',
                'price' => 649000,
                'image' => 'ao-len.jpg',
                'sku' => 'NOR-KNT-TEX',
                'colors' => ['Xám' => 'GRY', 'Xanh navy' => 'NVY'],
            ],
            [
                'slug' => 'ao-so-mi-color-pop',
                'name' => 'Áo sơ mi Color Pop',
                'category' => 'ao-so-mi-nam',
                'brand' => 'moc-daily',
                'description' => 'Sơ mi casual phom suông với màu sắc hiện đại. Chất liệu nhẹ giúp mặc thoải mái trong ngày dài.',
                'price' => 519000,
                'image' => 'ao-so-mi.jpg',
                'sku' => 'MOC-SHI-POP',
                'colors' => ['Xanh lá' => 'GRN', 'Đen' => 'BLK'],
            ],
            [
                'slug' => 'cardigan-warm-sand',
                'name' => 'Cardigan Warm Sand',
                'category' => 'ao-len-nu',
                'brand' => 'dai-nang',
                'description' => 'Cardigan mỏng màu cát với phom mềm và túi trước tiện dụng. Có thể khoác ngoài áo thun hoặc sơ mi.',
                'price' => 699000,
                'image' => 'bo-suu-tap-03.jpg',
                'sku' => 'DAN-CAR-SND',
                'colors' => ['Be' => 'BEI', 'Nâu nhạt' => 'TAN'],
            ],
            [
                'slug' => 'ao-so-mi-flannel-weekend',
                'name' => 'Áo sơ mi Flannel Weekend',
                'category' => 'ao-so-mi-nam',
                'brand' => 'northline',
                'description' => 'Sơ mi flannel caro có bề mặt mềm, phù hợp mặc riêng hoặc dùng như lớp áo khoác nhẹ.',
                'price' => 579000,
                'image' => 'bo-suu-tap-02.jpg',
                'sku' => 'NOR-FLA-WKD',
                'colors' => ['Tím than' => 'PLM', 'Xanh rêu' => 'OLV'],
            ],
            [
                'slug' => 'ao-khoac-knit-soft',
                'name' => 'Áo khoác Knit Soft',
                'category' => 'ao-len-nu',
                'brand' => 'dai-nang',
                'description' => 'Áo khoác dệt kim dáng gọn với tông nâu ấm. Chất vải co giãn nhẹ tạo cảm giác thoải mái khi di chuyển.',
                'price' => 729000,
                'image' => 'bo-suu-tap-01.jpg',
                'sku' => 'DAN-KNT-SFT',
                'colors' => ['Nâu' => 'BRN', 'Kem' => 'CRM'],
            ],
            [
                'slug' => 'ao-so-mi-linen-trang',
                'name' => 'Áo sơ mi Linen trắng',
                'category' => 'ao-so-mi-nu',
                'brand' => 'moc-daily',
                'description' => 'Sơ mi linen trắng tối giản, thoáng nhẹ và dễ kết hợp cùng denim. Phom relaxed tạo vẻ tự nhiên.',
                'price' => 619000,
                'image' => 'bo-suu-tap-04.jpg',
                'sku' => 'MOC-LIN-WHT',
                'colors' => ['Trắng' => 'WHT', 'Kem' => 'CRM'],
            ],
            [
                'slug' => 'ao-so-mi-pattern-office',
                'name' => 'Áo sơ mi Pattern Office',
                'category' => 'ao-so-mi-nam',
                'brand' => 'lang-studio',
                'description' => 'Sơ mi họa tiết nhỏ với cổ đứng gọn và phom smart casual. Phù hợp cho ngày làm việc hoặc buổi gặp gỡ cuối tuần.',
                'price' => 679000,
                'image' => 'bo-suu-tap-05.jpg',
                'sku' => 'LST-SHI-PAT',
                'colors' => ['Trắng họa tiết' => 'PRT', 'Xanh navy' => 'NVY'],
            ],
        ];

        foreach ($definitions as $definition) {
            $product = Product::withTrashed()->firstOrNew(['slug' => $definition['slug']]);
            $product->fill([
                'category_id' => $categories[$definition['category']]->id,
                'brand_id' => $brands[$definition['brand']]->id,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'base_price' => $definition['price'],
                'status' => 'active',
                'is_featured' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $product->save();
            $product->restore();

            $this->seedVariants($product, $definition, $admin);

            $product->images()->update(['is_primary' => false]);
            ProductImage::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'path' => $imagePaths[$definition['image']],
                ],
                [
                    'product_variant_id' => null,
                    'alt_text' => $definition['name'],
                    'sort_order' => 0,
                    'is_primary' => true,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedVariants(Product $product, array $definition, User $admin): void
    {
        foreach ($definition['colors'] as $color => $colorCode) {
            foreach (['S', 'M', 'L'] as $sizeIndex => $size) {
                $sku = $definition['sku'].'-'.$colorCode.'-'.$size;
                $variant = ProductVariant::withTrashed()->firstOrNew(['sku' => $sku]);
                $variant->fill([
                    'product_id' => $product->id,
                    'size' => $size,
                    'color' => $color,
                    'price' => null,
                    'stock_quantity' => 8 + ($sizeIndex * 4),
                    'low_stock_threshold' => 5,
                    'is_active' => true,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]);
                $variant->save();
                $variant->restore();
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function publishImages(): array
    {
        $paths = [];

        foreach (glob(database_path('seeders/'.self::ASSET_DIRECTORY.'/*.jpg')) ?: [] as $source) {
            $filename = basename($source);
            $path = self::STORAGE_DIRECTORY.'/'.$filename;
            $contents = file_get_contents($source);

            if ($contents === false || ! Storage::disk('s3')->put($path, $contents, ['visibility' => 'public'])) {
                throw new RuntimeException("Không thể đưa ảnh demo {$filename} lên disk s3.");
            }

            $paths[$filename] = $path;
        }

        if (count($paths) !== 8) {
            throw new RuntimeException('Bộ ảnh demo không đầy đủ. Cần đúng 8 tệp JPG.');
        }

        return $paths;
    }

    /**
     * @return array<string, string>
     */
    private function publishBrandLogos(): array
    {
        $definitions = [
            'lang-studio' => ['LS', '#17343a', '#e6f1ef'],
            'moc-daily' => ['MD', '#5b4636', '#f1e9dd'],
            'dai-nang' => ['DN', '#8a4938', '#fae7dc'],
            'northline' => ['NL', '#334155', '#e2e8f0'],
        ];
        $paths = [];

        foreach ($definitions as $slug => [$initials, $foreground, $background]) {
            $path = 'demo/brands/'.$slug.'.svg';
            $svg = <<<SVG
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 160">
                    <rect width="160" height="160" rx="32" fill="{$background}"/>
                    <circle cx="80" cy="80" r="54" fill="none" stroke="{$foreground}" stroke-width="3"/>
                    <text x="80" y="91" text-anchor="middle" font-family="Arial, sans-serif" font-size="38" font-weight="700" fill="{$foreground}">{$initials}</text>
                </svg>
                SVG;

            if (! Storage::disk('s3')->put($path, $svg, ['visibility' => 'public'])) {
                throw new RuntimeException("Không thể tạo logo demo {$slug} trên disk s3.");
            }

            $paths[$slug] = $path;
        }

        return $paths;
    }
}
