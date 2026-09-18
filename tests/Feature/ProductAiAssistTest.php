<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductAiAssistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        // The endpoint intentionally already ends in "/v1" here — that's the
        // base URL exactly as a provider hands it out, and the code must not
        // append another one (see OpenAiCompatibleProvider).
        config([
            'services.ai.openai_compatible.endpoint' => 'https://internal-ai.example.test/v1',
            'services.ai.openai_compatible.key' => 'test-key',
        ]);
    }

    private function url(): string
    {
        return route('admin.products.ai-assist');
    }

    /**
     * @return array<string, mixed>
     */
    private function textMessage(string $text): array
    {
        return ['messages' => [['role' => 'user', 'content' => [['type' => 'text', 'text' => $text]]]]];
    }

    private function draft(): array
    {
        return [
            'name' => 'Áo sơ mi trắng', 'description' => 'Chất liệu thoáng mát.',
            'bullets' => ['Form rộng', 'Vải cotton'], 'seo_title' => 'Áo sơ mi trắng nam',
            'price' => '350000', 'sizes' => ['S', 'M', 'L'], 'colors' => ['Trắng'],
            'category' => '', 'brand' => '', 'variant_images' => [], 'navigate' => '', 'set_fields' => [],
            'variant_price' => '', 'variant_stock' => '',
        ];
    }

    private function chatCompletionResponse(array $draft): array
    {
        return ['choices' => [['message' => ['content' => json_encode($draft)]]]];
    }

    public function test_request_requires_products_manage_permission(): void
    {
        $this->postJson($this->url(), $this->textMessage('áo thun'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson($this->url(), $this->textMessage('áo thun'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_successful_call_returns_the_parsed_draft_and_raw_reply(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($this->draft()))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo sơ mi trắng giá 350k size S M L'))
            ->assertOk()
            ->assertJsonPath('data.name', 'Áo sơ mi trắng')
            ->assertJsonPath('data.sizes', ['S', 'M', 'L'])
            ->assertJsonStructure(['data', 'raw']);
        Http::assertSentCount(1);
    }

    public function test_base_url_already_ending_in_v1_is_used_as_is(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($this->draft()))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))->assertOk();
        // Endpoint config already ends in "/v1" (see setUp) — the request
        // must go to exactly ".../v1/chat/completions", never ".../v1/v1/...".
        Http::assertSent(fn ($request) => $request->url() === 'https://internal-ai.example.test/v1/chat/completions');
    }

    public function test_active_category_and_brand_names_are_sent_to_the_provider(): void
    {
        Category::factory()->create(['name' => 'Áo thun', 'is_active' => true]);
        Category::factory()->create(['name' => 'Ngừng bán', 'is_active' => false]);
        Brand::factory()->create(['name' => 'Local Brand X', 'is_active' => true]);
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($this->draft()))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))->assertOk();
        Http::assertSent(function ($request) {
            $system = $request['messages'][0]['content'];

            return $request['messages'][0]['role'] === 'system'
                && str_contains($system, 'Áo thun') && ! str_contains($system, 'Ngừng bán') && str_contains($system, 'Local Brand X');
        });
    }

    public function test_category_brand_and_variant_images_pass_through_when_valid(): void
    {
        $draft = $this->draft();
        $draft['category'] = 'Áo thun';
        $draft['brand'] = 'Local Brand X';
        $draft['variant_images'] = [['color' => 'Trắng', 'image_index' => 1]];
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($draft))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertOk()
            ->assertJsonPath('data.category', 'Áo thun')
            ->assertJsonPath('data.brand', 'Local Brand X')
            ->assertJsonPath('data.variant_images', [['color' => 'Trắng', 'image_index' => 1]]);
    }

    public function test_page_directory_is_sent_to_the_provider(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($this->draft()))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))->assertOk();
        Http::assertSent(function ($request) {
            $system = $request['messages'][0]['content'];

            return str_contains($system, 'products.index: Danh sách sản phẩm');
        });
    }

    public function test_a_valid_navigate_key_resolves_to_its_real_url(): void
    {
        $draft = $this->draft();
        $draft['navigate'] = 'products.index';
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($draft))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('đưa tôi tới danh sách sản phẩm'))
            ->assertOk()
            ->assertJsonPath('navigate.key', 'products.index')
            ->assertJsonPath('navigate.label', 'Danh sách sản phẩm')
            ->assertJsonPath('navigate.url', route('admin.products.index'))
            ->assertJsonMissingPath('data.navigate');
    }

    public function test_an_unknown_navigate_key_resolves_to_null_instead_of_a_broken_link(): void
    {
        $draft = $this->draft();
        $draft['navigate'] = 'a-page-that-does-not-exist';
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($draft))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertOk()
            ->assertJsonPath('navigate', null);
    }

    public function test_an_empty_navigate_resolves_to_null(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($this->draft()))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertOk()
            ->assertJsonPath('navigate', null);
    }

    public function test_set_fields_pass_through_when_valid(): void
    {
        $draft = $this->draft();
        $draft['set_fields'] = [
            ['field' => 'price', 'value' => '100000'],
            ['field' => 'sizes', 'value' => ['S', 'XL']],
        ];
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($draft))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('giá 100k'))
            ->assertOk()
            ->assertJsonPath('data.set_fields', [
                ['field' => 'price', 'value' => '100000'],
                ['field' => 'sizes', 'value' => ['S', 'XL']],
            ]);
    }

    public function test_set_fields_entries_outside_the_whitelist_or_malformed_are_dropped(): void
    {
        $draft = $this->draft();
        $draft['set_fields'] = [
            ['field' => 'price', 'value' => '100000'],
            ['field' => 'description', 'value' => 'không được phép ghi đè mô tả'],
            // A "value" key that's missing entirely (as opposed to present
            // but empty) has nothing usable to apply, so it's dropped.
            ['field' => 'price'],
            'not-an-object',
            ['field' => 'unknown-field', 'value' => 'x'],
        ];
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($draft))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('giá 100k'))
            ->assertOk()
            ->assertJsonPath('data.set_fields', [['field' => 'price', 'value' => '100000']]);
    }

    public function test_set_fields_with_an_empty_value_is_kept_as_an_explicit_clear_signal(): void
    {
        $draft = $this->draft();
        $draft['set_fields'] = [
            ['field' => 'price', 'value' => ''],
            ['field' => 'sizes', 'value' => []],
        ];
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($draft))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('xoá giá đi'))
            ->assertOk()
            ->assertJsonPath('data.set_fields', [
                ['field' => 'price', 'value' => ''],
                ['field' => 'sizes', 'value' => []],
            ]);
    }

    public function test_variant_price_and_stock_pass_through_at_the_top_level_and_in_set_fields(): void
    {
        $draft = $this->draft();
        $draft['variant_price'] = '120000';
        $draft['variant_stock'] = '20';
        $draft['set_fields'] = [
            ['field' => 'variant_stock', 'value' => '20'],
        ];
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($draft))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('mỗi biến thể giá 120k tồn kho 20'))
            ->assertOk()
            ->assertJsonPath('data.variant_price', '120000')
            ->assertJsonPath('data.variant_stock', '20')
            ->assertJsonPath('data.set_fields', [['field' => 'variant_stock', 'value' => '20']]);
    }

    public function test_malformed_variant_image_entries_are_dropped_instead_of_failing(): void
    {
        $draft = $this->draft();
        $draft['variant_images'] = [
            ['color' => 'Trắng', 'image_index' => 1],
            ['color' => '', 'image_index' => 2],
            ['color' => 'Đen', 'image_index' => -1],
            ['color' => 'Xanh'],
            'not-an-object',
        ];
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($draft))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertOk()
            ->assertJsonPath('data.variant_images', [['color' => 'Trắng', 'image_index' => 1]]);
    }

    public function test_missing_fields_in_ai_reply_return_a_bad_gateway_error(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse(['name' => 'Áo']))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertStatus(502);
    }

    public function test_non_json_ai_reply_returns_a_bad_gateway_error(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => 'không phải JSON']]]])]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertStatus(502);
    }

    public function test_connection_failure_returns_a_retryable_message(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::failedConnection()]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertStatus(503);
    }

    public function test_provider_error_status_returns_a_retryable_message(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::response([], 500)]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertStatus(503);
    }

    public function test_call_is_rate_limited(): void
    {
        config(['services.ai.requests_per_minute' => 2]);
        Http::fake(['internal-ai.example.test/*' => Http::response($this->chatCompletionResponse($this->draft()))]);
        $this->actingAs(User::factory()->admin()->create());
        for ($i = 0; $i < 2; $i++) {
            $this->postJson($this->url(), $this->textMessage('áo thun'))->assertOk();
        }
        $this->postJson($this->url(), $this->textMessage('áo thun'))->assertStatus(429);
    }

    public function test_history_longer_than_the_configured_limit_is_rejected(): void
    {
        config(['services.ai.max_history_messages' => 2]);
        $messages = array_fill(0, 3, ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'áo thun']]]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), ['messages' => $messages])
            ->assertUnprocessable()->assertJsonValidationErrors('messages');
        Http::assertNothingSent();
    }

    public static function invalidImagePayloads(): array
    {
        return [
            'not base64' => ['not-base64-data!!'],
            'empty' => [''],
        ];
    }

    #[DataProvider('invalidImagePayloads')]
    public function test_invalid_image_data_is_rejected(string $data): void
    {
        $payload = ['messages' => [['role' => 'user', 'content' => [
            ['type' => 'image', 'media_type' => 'image/jpeg', 'data' => $data],
        ]]]];
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $payload)
            ->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_image_over_the_configured_size_limit_is_rejected(): void
    {
        config(['services.ai.max_image_kb' => 1]);
        $oversized = base64_encode(str_repeat('a', 2048));
        $payload = ['messages' => [['role' => 'user', 'content' => [
            ['type' => 'image', 'media_type' => 'image/jpeg', 'data' => $oversized],
        ]]]];
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $payload)
            ->assertUnprocessable();
        Http::assertNothingSent();
    }
}
