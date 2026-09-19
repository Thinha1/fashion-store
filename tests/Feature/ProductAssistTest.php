<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductAssistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'services.ai.openai_compatible.endpoint' => 'https://internal-ai.example.test/v1',
            'services.ai.openai_compatible.key' => 'test-key',
        ]);
    }

    private function url(): string
    {
        return route('products.assist');
    }

    /**
     * @return array<string, mixed>
     */
    private function textMessage(string $text): array
    {
        return ['messages' => [['role' => 'user', 'content' => $text]]];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function filterReply(array $overrides = []): array
    {
        return array_merge([
            'category' => '', 'price_min' => '', 'price_max' => '',
            'sizes' => [], 'colors' => [], 'keywords' => [],
            'reply' => 'Để mình tìm giúp bạn nhé!',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>
     */
    private function chatCompletionResponse(array $filter): array
    {
        return ['choices' => [['message' => ['content' => json_encode($filter)]]]];
    }

    public function test_guest_can_call_the_endpoint_without_logging_in(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($this->filterReply()))]);
        $this->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertOk()
            ->assertJsonStructure(['reply', 'products', 'raw']);
    }

    public function test_active_categories_are_sent_to_the_provider(): void
    {
        Category::factory()->create(['name' => 'Áo thun', 'is_active' => true]);
        Category::factory()->create(['name' => 'Ngừng bán', 'is_active' => false]);
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($this->filterReply()))]);
        $this->postJson($this->url(), $this->textMessage('áo thun'))->assertOk();
        Http::assertSent(function ($request) {
            $system = $request['messages'][0]['content'];

            return $request['messages'][0]['role'] === 'system'
                && str_contains($system, 'Áo thun') && ! str_contains($system, 'Ngừng bán');
        });
    }

    public function test_filter_resolves_to_matching_products_only(): void
    {
        $category = Category::factory()->create(['name' => 'Áo sơ mi', 'is_active' => true]);
        $matching = Product::factory()->create(['category_id' => $category->id, 'name' => 'Sơ mi trắng', 'base_price' => 300000]);
        ProductVariant::factory()->create(['product_id' => $matching->id, 'size' => 'M', 'color' => 'Trắng', 'stock_quantity' => 5]);

        $otherCategory = Category::factory()->create(['name' => 'Quần jean', 'is_active' => true]);
        $nonMatching = Product::factory()->create(['category_id' => $otherCategory->id, 'name' => 'Quần jean xanh', 'base_price' => 300000]);
        ProductVariant::factory()->create(['product_id' => $nonMatching->id, 'size' => 'M', 'color' => 'Trắng', 'stock_quantity' => 5]);

        $filter = $this->filterReply(['category' => 'Áo sơ mi', 'sizes' => ['M'], 'colors' => ['Trắng']]);
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($filter))]);

        $response = $this->postJson($this->url(), $this->textMessage('áo sơ mi trắng size M'))->assertOk();
        $names = collect($response->json('products'))->pluck('name');

        $this->assertTrue($names->contains('Sơ mi trắng'));
        $this->assertFalse($names->contains('Quần jean xanh'));
    }

    public function test_price_range_filters_out_products_outside_it(): void
    {
        $cheap = Product::factory()->create(['name' => 'Áo rẻ', 'base_price' => 100000]);
        $expensive = Product::factory()->create(['name' => 'Áo đắt', 'base_price' => 900000]);

        $filter = $this->filterReply(['price_min' => '200000', 'price_max' => '500000']);
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($filter))]);

        $response = $this->postJson($this->url(), $this->textMessage('áo giá 200-500k'))->assertOk();
        $names = collect($response->json('products'))->pluck('name');

        $this->assertFalse($names->contains('Áo rẻ'));
        $this->assertFalse($names->contains('Áo đắt'));
    }

    public function test_archived_products_never_appear_even_when_they_match(): void
    {
        Product::factory()->create(['name' => 'Sản phẩm ngừng bán', 'status' => 'archived']);
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($this->filterReply()))]);

        $response = $this->postJson($this->url(), $this->textMessage('áo thun'))->assertOk();

        $this->assertFalse(collect($response->json('products'))->pluck('name')->contains('Sản phẩm ngừng bán'));
    }

    public function test_an_unknown_category_from_the_model_is_ignored_instead_of_erroring(): void
    {
        Product::factory()->create(['name' => 'Áo bất kỳ']);
        $filter = $this->filterReply(['category' => 'Danh mục không tồn tại']);
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($filter))]);

        $this->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertOk()
            ->assertJsonCount(0, 'products');
    }

    public function test_reply_reports_the_real_match_count_from_the_server_not_the_model(): void
    {
        Product::factory()->count(2)->create();
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse(
            $this->filterReply(['reply' => 'Đây là gợi ý của bạn nhé, mình tìm được 999 sản phẩm luôn!'])
        ))]);

        $response = $this->postJson($this->url(), $this->textMessage('áo thun'))->assertOk();

        $this->assertStringContainsString('2 sản phẩm', $response->json('reply'));
        $this->assertStringNotContainsString('999', $response->json('reply'));
    }

    public function test_reply_says_nothing_found_when_the_query_matches_nothing(): void
    {
        $filter = $this->filterReply(['category' => '', 'keywords' => ['khôngkhớpgìcả']]);
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($filter))]);

        $response = $this->postJson($this->url(), $this->textMessage('áo màu tím than vũ trụ'))
            ->assertOk()
            ->assertJsonCount(0, 'products');

        $this->assertStringContainsString('chưa tìm thấy', $response->json('reply'));
    }

    public function test_non_json_ai_reply_returns_a_bad_gateway_error(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => 'không phải JSON']]]])]);
        $this->postJson($this->url(), $this->textMessage('áo thun'))->assertStatus(502);
    }

    public function test_connection_failure_returns_a_retryable_message(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::failedConnection()]);
        $this->postJson($this->url(), $this->textMessage('áo thun'))->assertStatus(503);
    }

    public function test_call_is_rate_limited_by_ip_for_guests(): void
    {
        config(['services.ai.shopping_assist_requests_per_minute' => 2]);
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($this->filterReply()))]);
        for ($i = 0; $i < 2; $i++) {
            $this->postJson($this->url(), $this->textMessage('áo thun'))->assertOk();
        }
        $this->postJson($this->url(), $this->textMessage('áo thun'))->assertStatus(429);
    }

    public function test_history_longer_than_the_configured_limit_is_rejected(): void
    {
        config(['services.ai.shopping_assist_max_history_messages' => 2]);
        $messages = array_fill(0, 3, ['role' => 'user', 'content' => 'áo thun']);
        $this->postJson($this->url(), ['messages' => $messages])
            ->assertUnprocessable()->assertJsonValidationErrors('messages');
        Http::assertNothingSent();
    }
}
