<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_a_repeatable_demo_catalog_with_images(): void
    {
        Storage::fake(config('filesystems.image_disk'));

        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('brands', 6);
        $this->assertDatabaseCount('categories', 23);
        $this->assertDatabaseCount('products', 118);
        $this->assertDatabaseCount('product_variants', 1447);
        $this->assertDatabaseCount('product_images', 426);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('size_charts', 13);

        $this->assertSame(23, Product::query()->where('is_featured', true)->count());
        $this->assertSame(2, Category::query()->whereNull('parent_id')->count());
        $this->assertSame(['Coolmate', 'Nike', 'Puma', 'Teelab', 'Uniqlo', 'Yame'], Brand::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame(53, Product::query()->whereHas('variants.activeDiscounts')->count());
        $this->assertSame(0, Product::query()->whereDoesntHave('images', fn ($query) => $query->where('is_primary', true))->count());
        $this->assertSame(323, ProductImage::query()->where('sort_order', 0)->whereNotNull('product_variant_id')->count());
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
        $this->assertEqualsCanonicalizing(
            ['M', 'L', 'XL', '2XL', '3XL'],
            $product->variants->where('color', 'Trắng')->pluck('size')->values()->all(),
        );
    }
}
