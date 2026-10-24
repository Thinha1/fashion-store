<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Demo catalog built from real products of Coolmate, Yame, Nike and Puma
 * (names, prices, sale prices, colors, sizes and photos as listed on their
 * Vietnamese stores, see assets/catalog/SOURCES.md). For the course project
 * only; the photos belong to the brands.
 */
class CatalogDemoSeeder extends Seeder
{
    private const ASSET_DIRECTORY = 'assets/catalog';

    private const STORAGE_DIRECTORY = 'demo/catalog';

    private const PRODUCT_DATA_FILE = 'seeders/data/catalog-products.json';

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
            'coolmate' => [
                'name' => 'Coolmate',
                'description' => 'Thương hiệu thời trang Việt Nam bán online, nổi tiếng với áo thun, polo và đồ thể thao cơ bản giá tốt.',
                'country' => 'Việt Nam',
            ],
            'yame' => [
                'name' => 'Yame',
                'description' => 'Thương hiệu thời trang nam Việt Nam với hệ thống cửa hàng toàn quốc, phong cách trẻ trung và giá dễ tiếp cận.',
                'country' => 'Việt Nam',
            ],
            'nike' => [
                'name' => 'Nike',
                'description' => 'Thương hiệu thể thao toàn cầu với các dòng Sportswear, Dri-FIT và Tech Fleece cho tập luyện lẫn mặc hằng ngày.',
                'country' => 'Mỹ',
            ],
            'puma' => [
                'name' => 'Puma',
                'description' => 'Thương hiệu thể thao đến từ Đức, nổi bật với dòng Essentials logo lớn và trang phục tập luyện.',
                'country' => 'Đức',
            ],
            'uniqlo' => [
                'name' => 'Uniqlo',
                'description' => 'Thương hiệu Nhật Bản với trang phục cơ bản chất lượng cao cho nam và nữ: AIRism, Supima Cotton, quần jeans EZY và áo khoác PUFFTECH.',
                'country' => 'Nhật Bản',
            ],
            'teelab' => [
                'name' => 'Teelab',
                'description' => 'Local brand Việt Nam phong cách streetwear unisex, nổi tiếng với áo thun, hoodie và áo khoác form rộng giá trẻ.',
                'country' => 'Việt Nam',
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
            ['slug' => 'nam', 'name' => 'Nam', 'parent' => null, 'image' => 'nike-solo-fleece-full-zip-hoodie-xam-dam.jpg'],
            ['slug' => 'nu', 'name' => 'Nữ', 'parent' => null, 'image' => 'nike-sportswear-mid-rise-pleated-skirt-den.jpg'],

            ['slug' => 'nam-ao', 'name' => 'Áo nam', 'parent' => 'nam', 'image' => 'ao-so-mi-dai-tay-oxford-premium-xanh-dam.jpg'],
            ['slug' => 'nam-quan', 'name' => 'Quần nam', 'parent' => 'nam', 'image' => 'quan-jeans-nam-basics-dang-slim-fit-den-wash.jpg'],
            ['slug' => 'nu-ao', 'name' => 'Áo nữ', 'parent' => 'nu', 'image' => 'ao-khoac-the-thao-nu-studio-be.jpg'],
            ['slug' => 'nu-quan', 'name' => 'Quần nữ', 'parent' => 'nu', 'image' => 'quan-wide-leg-nu-flex-active-den.jpg'],
            ['slug' => 'nu-vay', 'name' => 'Váy & đầm', 'parent' => 'nu', 'image' => 'dam-wardrobe-ess-midi-cut-out-den.jpg'],

            ['slug' => 'ao-thun-nam', 'name' => 'Áo thun', 'parent' => 'nam-ao', 'image' => 'ao-thun-nam-premium-cotton-den.jpg'],
            ['slug' => 'ao-polo-nam', 'name' => 'Áo polo', 'parent' => 'nam-ao', 'image' => 'ao-polo-nam-premium-pique-den.jpg'],
            ['slug' => 'ao-so-mi-nam', 'name' => 'Áo sơ mi', 'parent' => 'nam-ao', 'image' => 'ao-so-mi-dai-tay-oxford-premium-xanh-dam.jpg'],
            ['slug' => 'hoodie-nam', 'name' => 'Hoodie & nỉ', 'parent' => 'nam-ao', 'image' => 'ao-khoac-hoodie-nam-logo-lon-essentials-den.jpg'],
            ['slug' => 'ao-khoac-nam', 'name' => 'Áo khoác', 'parent' => 'nam-ao', 'image' => 'ao-khoac-du-ripstop-truot-nuoc-xam-nhat.jpg'],
            ['slug' => 'quan-jeans-nam', 'name' => 'Quần jeans', 'parent' => 'nam-quan', 'image' => 'quan-jeans-ong-suong-durabletex-xam-den.jpg'],
            ['slug' => 'quan-dai-nam', 'name' => 'Quần dài', 'parent' => 'nam-quan', 'image' => 'quan-tay-xep-ly-easycare-xam-dam.jpg'],
            ['slug' => 'quan-short-nam', 'name' => 'Quần short', 'parent' => 'nam-quan', 'image' => 'quan-short-lung-thun-van-noi-xam-trang.jpg'],
            ['slug' => 'ao-so-mi-nu', 'name' => 'Áo sơ mi', 'parent' => 'nu-ao', 'image' => 'ao-so-mi-vai-linen-cao-cap-trang.jpg'],
            ['slug' => 'quan-jeans-nu', 'name' => 'Quần jeans', 'parent' => 'nu-quan', 'image' => 'quan-jeans-ezy-dang-rong-ong-suong-xanh-duong.jpg'],
            ['slug' => 'ao-thun-nu', 'name' => 'Áo thun', 'parent' => 'nu-ao', 'image' => 'nike-sportswear-women-s-t-shirt-hong-phan.jpg'],
            ['slug' => 'hoodie-nu', 'name' => 'Hoodie & nỉ', 'parent' => 'nu-ao', 'image' => 'nike-studio-fleece-oversized-full-zip-hoodie-den.jpg'],
            ['slug' => 'ao-khoac-nu', 'name' => 'Áo khoác', 'parent' => 'nu-ao', 'image' => 'ao-khoac-the-thao-nu-studio-be.jpg'],
            ['slug' => 'quan-dai-nu', 'name' => 'Quần dài', 'parent' => 'nu-quan', 'image' => 'quan-ni-nu-ong-rong-wardrobe-essentials-relaxed-hong.jpg'],
            ['slug' => 'chan-vay', 'name' => 'Chân váy', 'parent' => 'nu-vay', 'image' => 'nike-sportswear-mid-rise-pleated-skirt-den.jpg'],
            ['slug' => 'dam-nu', 'name' => 'Đầm', 'parent' => 'nu-vay', 'image' => 'dam-wardrobe-ess-midi-cut-out-den.jpg'],
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
                    'description' => 'Danh mục '.mb_strtolower($definition['name']).($parent ? ' - '.$parent->name : '').'.',
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
        foreach ($this->productDefinitions() as $definition) {
            $product = Product::withTrashed()->firstOrNew(['slug' => $definition['slug']]);
            $product->fill([
                'category_id' => $categories[$definition['category']]->id,
                'brand_id' => $brands[$definition['brand']]->id,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'base_price' => $definition['price'],
                'status' => 'active',
                'is_featured' => (bool) ($definition['featured'] ?? false),
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $product->save();
            $product->restore();

            $variants = $this->seedVariants($product, $definition, $admin);
            $this->seedDiscounts($variants, $definition, $admin);
            $this->seedImages($product, $variants, $definition, $imagePaths, $admin);
        }
    }

    /**
     * One photo per color, attached to that color's first variant so the
     * product page swaps pictures when a color is picked. The first color's
     * photo is the primary (card) image; gallery shots follow it.
     *
     * @param  list<ProductVariant>  $variants
     * @param  array<string, mixed>  $definition
     * @param  array<string, string>  $imagePaths
     */
    private function seedImages(Product $product, array $variants, array $definition, array $imagePaths, User $admin): void
    {
        $product->images()->delete();
        $firstColor = array_key_first($definition['colors']);
        $variantForColor = fn (string $color): ?int => collect($variants)->firstWhere('color', $color)?->id;

        foreach ($definition['color_images'] as $color => $file) {
            ProductImage::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variantForColor($color),
                'path' => $imagePaths[$file],
                'alt_text' => $definition['name'].' - '.mb_strtolower($color),
                'sort_order' => 0,
                'is_primary' => $color === $firstColor,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        }

        foreach ($definition['gallery'] ?? [] as $index => $file) {
            ProductImage::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variantForColor($firstColor),
                'path' => $imagePaths[$file],
                'alt_text' => $definition['name'],
                'sort_order' => $index + 1,
                'is_primary' => false,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productDefinitions(): array
    {
        $contents = file_get_contents(database_path(self::PRODUCT_DATA_FILE));

        if ($contents === false) {
            throw new RuntimeException('Không thể đọc dữ liệu sản phẩm demo.');
        }

        $definitions = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($definitions)) {
            throw new RuntimeException('Dữ liệu sản phẩm demo không hợp lệ.');
        }

        return $definitions;
    }

    /**
     * Every color in every size. Stock is derived from the SKU so re-seeding
     * gives the same numbers: most variants are well stocked, a few are low
     * (dashboard alert) and a few are sold out (catalog "Hết hàng").
     *
     * @param  array<string, mixed>  $definition
     * @return list<ProductVariant>
     */
    private function seedVariants(Product $product, array $definition, User $admin): array
    {
        $variants = [];

        foreach ($definition['colors'] as $color => $colorCode) {
            foreach ($definition['sizes'] as $size) {
                $sku = $definition['sku'].'-'.$colorCode.'-'.$size;
                $variant = ProductVariant::withTrashed()->firstOrNew(['sku' => $sku]);
                $variant->fill([
                    'product_id' => $product->id,
                    'size' => $size,
                    'color' => $color,
                    'price' => null,
                    'stock_quantity' => $this->stockFor($sku),
                    'low_stock_threshold' => 5,
                    'is_active' => true,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]);
                $variant->save();
                $variant->restore();
                $variants[] = $variant;
            }
        }

        return $variants;
    }

    private function stockFor(string $sku): int
    {
        $roll = crc32($sku) % 100;

        return match (true) {
            $roll < 6 => 0,
            $roll < 16 => 1 + $roll % 4,
            default => 8 + $roll % 25,
        };
    }

    /**
     * The brand's own sale (listed price below its original price) becomes
     * a running percentage discount on every variant of the product.
     *
     * @param  list<ProductVariant>  $variants
     * @param  array<string, mixed>  $definition
     */
    private function seedDiscounts(array $variants, array $definition, User $admin): void
    {
        foreach ($variants as $variant) {
            if (! isset($definition['discount_percent'])) {
                Discount::query()->where('product_variant_id', $variant->id)->where('scope', 'variant')->delete();

                continue;
            }

            Discount::query()->updateOrCreate(
                ['product_variant_id' => $variant->id, 'scope' => 'variant'],
                [
                    'code' => null,
                    'discount_type' => 'percent',
                    'discount_value' => $definition['discount_percent'],
                    'starts_at' => now()->subDays(3)->startOfDay(),
                    'ends_at' => now()->addMonths(2)->endOfDay(),
                    'is_active' => true,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
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

            if ($contents === false || ! Storage::disk(config('filesystems.image_disk'))->put($path, $contents, ['visibility' => 'public'])) {
                throw new RuntimeException("Không thể đưa ảnh demo {$filename} lên disk s3.");
            }

            $paths[$filename] = $path;
        }

        foreach ($this->productDefinitions() as $definition) {
            foreach ([...array_values($definition['color_images']), ...($definition['gallery'] ?? [])] as $file) {
                if (! isset($paths[$file])) {
                    throw new RuntimeException("Thiếu ảnh demo {$file} cho sản phẩm {$definition['slug']}.");
                }
            }
        }

        return $paths;
    }

    /**
     * @return array<string, string>
     */
    private function publishBrandLogos(): array
    {
        $definitions = [
            'coolmate' => ['CM', '#1f2937', '#e0f2fe'],
            'yame' => ['YM', '#7c2d12', '#ffedd5'],
            'nike' => ['NK', '#111827', '#f3f4f6'],
            'puma' => ['PM', '#991b1b', '#fee2e2'],
            'uniqlo' => ['UQ', '#ffffff', '#dc2626'],
            'teelab' => ['TL', '#f9fafb', '#111827'],
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

            if (! Storage::disk(config('filesystems.image_disk'))->put($path, $svg, ['visibility' => 'public'])) {
                throw new RuntimeException("Không thể tạo logo demo {$slug} trên disk s3.");
            }

            $paths[$slug] = $path;
        }

        return $paths;
    }
}
