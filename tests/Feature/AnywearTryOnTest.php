<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnywearTryOnTest extends TestCase
{
    use RefreshDatabase;

    private function activeProduct(): Product
    {
        $product = Product::factory()->create(['status' => 'active']);
        ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);

        return $product;
    }

    public function test_product_page_loads_the_anywear_widget_for_the_configured_domain(): void
    {
        config(['store.anywear_domain' => 'shop.example.com']);

        $this->get(route('products.show', $this->activeProduct()))->assertOk()
            ->assertSee('https://anywear.decart.ai/widget/latest/anywear.js?domain=shop.example.com', false);
    }

    public function test_product_page_leaves_the_anywear_widget_out_when_no_domain_is_set(): void
    {
        config(['store.anywear_domain' => null]);

        $this->get(route('products.show', $this->activeProduct()))->assertOk()
            ->assertDontSee('anywear.decart.ai', false)
            ->assertDontSee('Thử ngay');
    }

    public function test_product_page_relabels_the_anywear_button_in_vietnamese(): void
    {
        config(['store.anywear_domain' => 'shop.example.com']);

        $this->get(route('products.show', $this->activeProduct()))->assertOk()
            ->assertSee('.vton-btn .vton-label::after { content: "Thử ngay"; font-size: 11px; }', false)
            ->assertSee('data-testid="add-to-cart"', false);
    }

    public function test_product_page_describes_the_product_as_schema_org_json_ld(): void
    {
        $product = $this->activeProduct();
        $product->update(['name' => 'Áo </script> thun']);

        $html = $this->get(route('products.show', $product))->assertOk()->getContent();

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);
        $jsonLd = json_decode($match[1] ?? '', true);

        $this->assertSame('Product', $jsonLd['@type'] ?? null);
        $this->assertSame('Áo </script> thun', $jsonLd['name']);
        $this->assertSame('VND', $jsonLd['offers']['priceCurrency']);
    }

    public function test_other_storefront_pages_never_load_the_anywear_widget(): void
    {
        config(['store.anywear_domain' => 'shop.example.com']);
        $this->activeProduct();

        $this->get(route('products.index'))->assertOk()->assertDontSee('anywear.decart.ai', false);
    }
}
