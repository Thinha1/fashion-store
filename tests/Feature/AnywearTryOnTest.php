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
            ->assertDontSee('anywear.decart.ai', false);
    }

    public function test_other_storefront_pages_never_load_the_anywear_widget(): void
    {
        config(['store.anywear_domain' => 'shop.example.com']);
        $this->activeProduct();

        $this->get(route('products.index'))->assertOk()->assertDontSee('anywear.decart.ai', false);
    }
}
