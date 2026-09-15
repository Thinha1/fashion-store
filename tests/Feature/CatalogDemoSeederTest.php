<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_a_repeatable_demo_catalog_with_images(): void
    {
        Storage::fake('s3');

        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('brands', 4);
        $this->assertDatabaseCount('categories', 18);
        $this->assertDatabaseCount('products', 8);
        $this->assertDatabaseCount('product_variants', 48);
        $this->assertDatabaseCount('product_images', 8);
        $this->assertDatabaseCount('users', 1);

        $this->assertSame(8, Product::query()->where('is_featured', true)->count());
        $this->assertSame(3, Category::query()->whereNull('parent_id')->count());
        $this->assertSame(4, Brand::query()->where('is_active', true)->count());

        $product = Product::query()
            ->where('slug', 'ao-thun-essential-trang')
            ->with(['brand', 'category', 'variants', 'images'])
            ->firstOrFail();

        $this->assertSame('Lặng Studio', $product->brand->name);
        $this->assertSame('Áo thun', $product->category->name);
        $this->assertCount(6, $product->variants);
        $this->assertCount(1, $product->images);
        $this->assertTrue($product->images->first()->is_primary);
        $this->assertGreaterThan(0, $product->variants->sum('stock_quantity'));

        Storage::disk('s3')->assertExists('demo/catalog/ao-thun.jpg');
        Storage::disk('s3')->assertExists('demo/brands/lang-studio.svg');
        $this->assertSame(
            6,
            ProductVariant::query()->where('product_id', $product->id)->count(),
        );
    }
}
