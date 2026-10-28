<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CatalogDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Photos the seeder expects to find already uploaded on the image disk
     * (the high-resolution Levents and Routine shots kept out of the repo).
     *
     * @return list<string>
     */
    private function remoteOnlyPhotos(): array
    {
        $products = json_decode((string) file_get_contents(database_path('seeders/data/catalog-products.json')), true);
        $files = collect($products)->flatMap(fn (array $product) => [...array_values($product['color_images']), ...($product['gallery'] ?? [])]);

        return $files->unique()
            ->reject(fn (string $file) => is_file(database_path('seeders/assets/catalog/'.$file)))
            ->values()
            ->all();
    }

    private function fakeUploadedPhotos(array $files): void
    {
        foreach ($files as $file) {
            Storage::disk(config('filesystems.image_disk'))->put('demo/catalog/'.$file, 'jpeg');
        }
    }

    public function test_database_seeder_creates_a_repeatable_demo_catalog_with_images(): void
    {
        Storage::fake(config('filesystems.image_disk'));
        $this->fakeUploadedPhotos($this->remoteOnlyPhotos());

        $this->seed();

        // Between runs: a stored photo with the repo file's size is left
        // alone, one whose size differs is uploaded again.
        $disk = Storage::disk(config('filesystems.image_disk'));
        $sameSize = 'ao-thun-nam-premium-cotton-den.jpg';
        $changed = 'ao-thun-nam-premium-cotton-trang.jpg';
        $placeholder = str_repeat('x', filesize(database_path('seeders/assets/catalog/'.$sameSize)));
        $disk->put('demo/catalog/'.$sameSize, $placeholder);
        $disk->put('demo/catalog/'.$changed, 'stale');

        $this->seed();

        $this->assertSame($placeholder, $disk->get('demo/catalog/'.$sameSize));
        $this->assertSame(file_get_contents(database_path('seeders/assets/catalog/'.$changed)), $disk->get('demo/catalog/'.$changed));

        $this->assertDatabaseCount('brands', 9);
        $this->assertDatabaseCount('categories', 23);
        $this->assertDatabaseCount('products', 162);
        $this->assertDatabaseCount('product_variants', 1759);
        $this->assertDatabaseCount('product_images', 506);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('size_charts', 13);

        $this->assertSame(27, Product::query()->where('is_featured', true)->count());
        $this->assertSame(2, Category::query()->whereNull('parent_id')->count());
        $this->assertSame(['Alo Yoga', 'Coolmate', 'Levents', 'Nike', 'Puma', 'Routine', 'Teelab', 'Uniqlo', 'Yame'], Brand::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame(63, Product::query()->whereHas('variants.activeDiscounts')->count());
        $this->assertSame(0, Product::query()->whereDoesntHave('images', fn ($query) => $query->where('is_primary', true))->count());
        $this->assertSame(403, ProductImage::query()->where('sort_order', 0)->whereNotNull('product_variant_id')->count());
        $this->assertTrue(ProductVariant::query()->where('stock_quantity', 0)->exists());
        $this->assertTrue(ProductVariant::query()->whereBetween('stock_quantity', [1, 5])->exists());
        $this->assertSame(0, Category::query()->whereNotNull('parent_id')->whereDoesntHave('children')->whereDoesntHave('products')->count());

        $product = Product::query()
            ->where('slug', 'ao-thun-nam-premium-cotton')
            ->with(['brand', 'category', 'variants', 'images'])
            ->firstOrFail();

        $this->assertSame('Coolmate', $product->brand->name);
        $this->assertSame('Áo thun', $product->category->name);
        $this->assertCount(15, $product->variants);
        $this->assertCount(5, $product->images);
        $this->assertSame(1, $product->images->where('is_primary', true)->count());
        $this->assertEqualsCanonicalizing(
            ['Đen', 'Trắng', 'Xanh navy'],
            $product->images->load('variant')->pluck('variant.color')->unique()->values()->all(),
        );
        $this->assertGreaterThan(0, $product->variants->sum('stock_quantity'));

        Storage::disk(config('filesystems.image_disk'))->assertExists('demo/catalog/ao-thun-nam-premium-cotton-den.jpg');
        Storage::disk(config('filesystems.image_disk'))->assertExists('demo/brands/coolmate.svg');
        Storage::disk(config('filesystems.image_disk'))->assertExists('demo/brands/levents.svg');
        Storage::disk(config('filesystems.image_disk'))->assertExists('demo/brands/alo.svg');
        $this->assertEqualsCanonicalizing(
            ['M', 'L', 'XL', '2XL', '3XL'],
            $product->variants->where('color', 'Trắng')->pluck('size')->values()->all(),
        );

        $hoodie = Product::query()->where('slug', 'levents-xl-logo-zipper-hoodie-boxy')->with('images')->firstOrFail();
        $this->assertSame('Levents', $hoodie->brand->name);
        $this->assertSame('demo/catalog/levents-xl-logo-zipper-hoodie-boxy-nau.jpg', $hoodie->images->firstWhere('is_primary', true)->path);

        $legging = Product::query()->where('slug', 'alo-yoga-airbrush-high-waist-heart-throb-legging')->with(['brand', 'category', 'variants'])->firstOrFail();
        $this->assertSame('Alo Yoga', $legging->brand->name);
        $this->assertSame('Mỹ', $legging->brand->country);
        $this->assertSame('quan-dai-nu', $legging->category->slug);
        $this->assertSame(3480000, (int) $legging->base_price);
        $this->assertEqualsCanonicalizing(['Tím nhạt', 'Hồng'], $legging->variants->pluck('color')->unique()->values()->all());
    }

    public function test_seeder_stops_when_a_photo_is_neither_in_the_repo_nor_on_the_image_disk(): void
    {
        Storage::fake(config('filesystems.image_disk'));
        $missing = $this->remoteOnlyPhotos();
        $this->fakeUploadedPhotos(array_slice($missing, 1));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Thiếu ảnh demo '.$missing[0]);

        $this->seed();
    }
}
