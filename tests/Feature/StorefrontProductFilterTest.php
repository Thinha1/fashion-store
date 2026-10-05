<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProductFilterTest extends TestCase
{
    use RefreshDatabase;

    private function productWith(string $name, array $attributes = [], array $variant = []): Product
    {
        $product = Product::factory()->create(['name' => $name, 'status' => 'active', ...$attributes]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true, 'size' => 'M', 'color' => 'Đen', ...$variant]);

        return $product;
    }

    public function test_a_single_brand_slug_in_the_query_string_still_filters(): void
    {
        $brand = Brand::factory()->create(['name' => 'Mộc Daily']);
        $this->productWith('Áo của Mộc', ['brand_id' => $brand->id]);
        $this->productWith('Áo của hãng khác');

        $this->get(route('products.index', ['brand' => $brand->slug]))
            ->assertOk()
            ->assertSee('Áo của Mộc')
            ->assertDontSee('Áo của hãng khác');
    }

    public function test_several_brands_can_be_selected_at_once(): void
    {
        $first = Brand::factory()->create();
        $second = Brand::factory()->create();
        $this->productWith('Áo hãng một', ['brand_id' => $first->id]);
        $this->productWith('Áo hãng hai', ['brand_id' => $second->id]);
        $this->productWith('Áo hãng ba');

        $this->get(route('products.index', ['brand' => [$first->slug, $second->slug]]))
            ->assertSee('Áo hãng một')
            ->assertSee('Áo hãng hai')
            ->assertDontSee('Áo hãng ba');
    }

    public function test_price_range_keeps_only_products_inside_it(): void
    {
        $this->productWith('Áo rẻ', ['base_price' => 300000]);
        $this->productWith('Áo vừa', ['base_price' => 600000]);
        $this->productWith('Áo đắt', ['base_price' => 900000]);

        $this->get(route('products.index', ['price_min' => '500.000', 'price_max' => 700000]))
            ->assertSee('Áo vừa')
            ->assertDontSee('Áo rẻ')
            ->assertDontSee('Áo đắt');
    }

    public function test_size_filter_matches_active_variants_only(): void
    {
        $this->productWith('Áo có size L', variant: ['size' => 'L']);
        $this->productWith('Áo chỉ có size M');
        $inactive = $this->productWith('Áo size L đã ngừng', variant: ['size' => 'L', 'is_active' => false]);

        $this->get(route('products.index', ['size' => ['L']]))
            ->assertSee('Áo có size L')
            ->assertDontSee('Áo chỉ có size M')
            ->assertDontSee($inactive->name);
    }

    public function test_color_filter_matches_variant_colour(): void
    {
        $this->productWith('Áo màu kem', variant: ['color' => 'Kem']);
        $this->productWith('Áo màu đen');

        $this->get(route('products.index', ['color' => ['Kem']]))
            ->assertSee('Áo màu kem')
            ->assertDontSee('Áo màu đen');
    }

    public function test_an_unknown_size_is_ignored_instead_of_hiding_everything(): void
    {
        $this->productWith('Áo bất kỳ');

        $this->get(route('products.index', ['size' => ['XXXL-khong-co']]))
            ->assertOk()
            ->assertSee('Áo bất kỳ');
    }

    public function test_no_match_shows_the_empty_state_with_a_way_out(): void
    {
        $this->productWith('Áo bất kỳ', ['base_price' => 100000]);

        $this->get(route('products.index', ['price_min' => 5000000]))
            ->assertOk()
            ->assertSee('Chưa có sản phẩm khớp bộ lọc')
            ->assertSee('Xóa bộ lọc');
    }

    public function test_active_filters_are_listed_with_a_count_on_the_filter_button(): void
    {
        $brand = Brand::factory()->create(['name' => 'Mộc Daily']);
        $this->productWith('Áo của Mộc', ['brand_id' => $brand->id]);

        $this->get(route('products.index', ['brand' => [$brand->slug], 'size' => ['M']]))
            ->assertSee('Đang lọc:')
            ->assertSee('Bỏ lọc thương hiệu Mộc Daily', false)
            ->assertSee('Bỏ lọc size M', false);
    }

    public function test_the_card_shows_how_many_colours_a_product_comes_in(): void
    {
        $product = $this->productWith('Áo hai màu', variant: ['color' => 'Kem']);
        ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true, 'size' => 'L', 'color' => 'Đen']);

        $this->get(route('products.index'))->assertSee('2 màu');
    }

    public function test_pagination_summary_is_in_vietnamese(): void
    {
        Product::factory()->count(13)->create(['status' => 'active']);

        $this->get(route('products.index'))
            ->assertSee('Hiển thị 1–12 trong 13 sản phẩm')
            ->assertDontSee('Showing');
    }

    public function test_a_product_without_photos_still_renders_a_framed_placeholder(): void
    {
        $product = $this->productWith('Áo chưa có ảnh');

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Chưa có ảnh cho sản phẩm này')
            ->assertSee('Hỏi trợ lý về sản phẩm này');
    }
}
