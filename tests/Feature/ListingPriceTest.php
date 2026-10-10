<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ListingPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_tag_shows_the_cheapest_variant_after_its_discount_and_the_deepest_discount(): void
    {
        $product = Product::factory()->create(['base_price' => 500000]);
        $cheap = ProductVariant::factory()->for($product)->create(['price' => 300000]);
        $pricey = ProductVariant::factory()->for($product)->create(['price' => 400000]);
        Discount::factory()->for($cheap)->create(['discount_type' => 'percent', 'discount_value' => 10]);
        Discount::factory()->for($pricey)->create(['discount_type' => 'percent', 'discount_value' => 30]);

        $tag = (new PriceCalculator)->priceTag(Product::query()->withListingPrices()->find($product->id));

        $this->assertSame(270000, $tag->price);
        $this->assertSame(300000, $tag->originalPrice);
        $this->assertSame(30, $tag->maxDiscountPercent);
        $this->assertTrue($tag->isDiscounted());
    }

    public function test_inactive_variants_are_ignored_and_no_variants_means_base_price(): void
    {
        $product = Product::factory()->create(['base_price' => 500000]);
        ProductVariant::factory()->for($product)->inactive()->create(['price' => 100000]);
        $bare = Product::factory()->create(['base_price' => 250000]);

        $prices = new PriceCalculator;
        $tag = $prices->priceTag(Product::query()->withListingPrices()->find($product->id));
        $bareTag = $prices->priceTag(Product::query()->withListingPrices()->find($bare->id));

        $this->assertSame(500000, $tag->price);
        $this->assertFalse($tag->isDiscounted());
        $this->assertSame(250000, $bareTag->price);
        $this->assertSame(0, $bareTag->maxDiscountPercent);
    }

    public function test_catalog_home_and_collection_cards_show_the_discounted_price(): void
    {
        $brand = Brand::factory()->create(['is_active' => true]);
        $product = Product::factory()->for($brand)->featured()->create(['base_price' => 300000]);
        $variant = ProductVariant::factory()->for($product)->create(['price' => 300000, 'stock_quantity' => 3]);
        Discount::factory()->for($variant)->create(['discount_type' => 'percent', 'discount_value' => 20]);

        foreach ([route('products.index'), route('home'), route('collections.show', $brand)] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('240.000 ₫')
                ->assertSee('300.000 ₫')
                ->assertSee('-20%');
        }
    }

    public function test_catalog_page_does_not_query_per_product_for_prices(): void
    {
        foreach (range(1, 6) as $index) {
            $variant = ProductVariant::factory()->create(['stock_quantity' => 3]);
            Discount::factory()->for($variant)->create();
        }

        DB::enableQueryLog();
        $this->get(route('products.index'))->assertOk();
        $queries = count(DB::getQueryLog());

        foreach (range(1, 6) as $index) {
            $variant = ProductVariant::factory()->create(['stock_quantity' => 3]);
            Discount::factory()->for($variant)->create();
        }

        DB::flushQueryLog();
        $this->get(route('products.index'))->assertOk();

        $this->assertSame($queries, count(DB::getQueryLog()));
    }
}
