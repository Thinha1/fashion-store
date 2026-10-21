<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Ai\ProductDraftResponseParser;
use App\Support\ColorPalette;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColorPaletteTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_text_colors_are_matched_to_palette_names(): void
    {
        $this->assertSame('Xanh navy', ColorPalette::match('xanh NAVY'));
        $this->assertSame('Đen', ColorPalette::match('den'));
        $this->assertSame('Đen', ColorPalette::match('Black'));
        $this->assertSame('Xám', ColorPalette::match('ghi'));
        $this->assertSame('Xanh dương', ColorPalette::match('Xanh'));
        $this->assertNull(ColorPalette::match('Cầu vồng'));
        $this->assertNull(ColorPalette::match(''));

        $this->assertSame('#4b5320', ColorPalette::hex('Xanh rêu'));
        $this->assertSame('#e5e7eb', ColorPalette::hex('Không có'));
    }

    public function test_product_form_only_accepts_palette_colors(): void
    {
        $payload = [
            'name' => 'Áo thun',
            'slug' => 'ao-thun-palette',
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
            'base_price' => 199000,
            'status' => 'active',
            'variants' => [
                ['size' => 'M', 'color' => 'Cầu vồng', 'sku' => 'PAL-M', 'stock_quantity' => 5, 'low_stock_threshold' => 5],
            ],
        ];

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.products.store'), $payload)
            ->assertSessionHasErrors(['variants.0.color' => 'Màu phải chọn trong bảng màu.']);

        $payload['variants'][0]['color'] = 'Xanh rêu';
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.products.store'), $payload)
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_variants', ['sku' => 'PAL-M', 'color' => 'Xanh rêu']);
    }

    public function test_variant_row_offers_the_palette_and_flags_off_palette_colors(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create(['color' => 'Màu cũ lạ']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('<option value="Xanh rêu"', false)
            ->assertSee('Màu cũ lạ (ngoài bảng màu, chọn lại)');
    }

    public function test_product_page_swatches_use_palette_colors(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create(['color' => 'Xanh rêu', 'size' => 'M']);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('--swatch-color: #4b5320', false);
    }

    public function test_ai_draft_colors_are_snapped_to_the_palette(): void
    {
        $draft = (new ProductDraftResponseParser)->parse(json_encode([
            'name' => 'Áo', 'description' => 'Mô tả', 'bullets' => [], 'seo_title' => '', 'price' => '',
            'sizes' => ['M'], 'colors' => ['black', 'Xanh Navy', 'Cầu vồng', 'đen'],
            'category' => '', 'brand' => '', 'variant_images' => [['color' => 'navy', 'image_index' => 1]],
            'set_fields' => [['field' => 'colors', 'value' => ['white', 'tím than']]],
        ]));

        $this->assertSame(['Đen', 'Xanh navy'], $draft['colors']);
        $this->assertSame([['color' => 'Xanh navy', 'image_index' => 1]], $draft['variant_images']);
        $this->assertSame([['field' => 'colors', 'value' => ['Trắng']]], $draft['set_fields']);
    }
}
