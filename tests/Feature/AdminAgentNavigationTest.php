<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminAgentNavigationTest extends TestCase
{
    use RefreshDatabase;

    private Product $polo;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'services.ai.openai_compatible.endpoint' => 'https://internal-ai.example.test/v1',
            'services.ai.openai_compatible.key' => 'test-key',
        ]);

        $this->polo = Product::factory()->for(Brand::factory()->create(['name' => 'Coolmate']))->create(['name' => 'Áo Polo nam Premium Pique']);
        ProductVariant::factory()->for($this->polo)->create(['sku' => 'CM-AOPONAPR-D-M']);
        Product::factory()->for(Brand::factory()->create(['name' => 'Nike']))->create(['name' => 'Nike Club Hooded Jacket']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function ask(array $overrides, string $message = 'mở sản phẩm'): TestResponse
    {
        $reply = array_merge([
            'name' => '', 'description' => '', 'bullets' => [], 'seo_title' => '', 'price' => '',
            'sizes' => [], 'colors' => [], 'category' => '', 'brand' => '', 'variant_images' => [],
            'navigate' => '', 'navigate_product' => '', 'set_fields' => [], 'variant_price' => '', 'variant_stock' => '',
        ], $overrides);
        Http::fake(['internal-ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode($reply)]]]])]);

        return $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.products.ai-assist'), ['messages' => [['role' => 'user', 'content' => [['type' => 'text', 'text' => $message]]]]]);
    }

    public function test_the_orders_page_is_in_the_page_directory(): void
    {
        $this->ask(['navigate' => 'orders.index'], 'mở danh sách đơn hàng')
            ->assertOk()
            ->assertJsonPath('navigate.key', 'orders.index')
            ->assertJsonPath('navigate.label', 'Danh sách đơn hàng')
            ->assertJsonPath('navigate.url', route('admin.orders.index'));

        Http::assertSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'], 'orders.index: Danh sách đơn hàng'));
    }

    public function test_a_product_named_in_the_chat_opens_its_edit_page(): void
    {
        $this->ask(['navigate_product' => 'polo coolmate'], 'mở áo polo coolmate')
            ->assertOk()
            ->assertJsonPath('navigate.key', 'products.edit')
            ->assertJsonPath('navigate.label', 'sửa sản phẩm “Áo Polo nam Premium Pique”')
            ->assertJsonPath('navigate.url', route('admin.products.edit', $this->polo))
            ->assertJsonPath('notice', null)
            ->assertJsonMissingPath('data.navigate_product');
    }

    public function test_a_sku_opens_its_product_in_any_letter_case(): void
    {
        $this->ask(['navigate_product' => 'cm-aoponapr-d-m'])
            ->assertJsonPath('navigate.url', route('admin.products.edit', $this->polo));
    }

    public function test_archived_products_can_be_opened_too(): void
    {
        $archived = Product::factory()->create(['name' => 'Quần short cũ', 'status' => 'archived']);

        $this->ask(['navigate_product' => 'short cũ'])
            ->assertJsonPath('navigate.url', route('admin.products.edit', $archived));
    }

    public function test_an_unknown_product_tells_the_staff_member_instead_of_navigating(): void
    {
        $this->ask(['navigate_product' => 'áo len cashmere'])
            ->assertOk()
            ->assertJsonPath('navigate', null)
            ->assertJsonPath('notice', 'Không tìm thấy sản phẩm “áo len cashmere”. Bạn thử gọi bằng tên khác hoặc SKU nhé.');
    }

    public function test_edits_asked_together_with_opening_a_product_are_kept_for_that_page(): void
    {
        $this->ask([
            'navigate_product' => 'Nike Club Hooded Jacket',
            'set_fields' => [['field' => 'price', 'value' => '350000']],
        ], 'mở Nike Club Hooded Jacket rồi đổi giá 350k')
            ->assertOk()
            ->assertJsonPath('navigate.key', 'products.edit')
            ->assertJsonPath('data.set_fields', [['field' => 'price', 'value' => '350000']]);
    }
}
