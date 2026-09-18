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
        config(['services.ai.provider' => 'anthropic', 'services.ai.anthropic.key' => 'test-key']);
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
            'category' => '', 'brand' => '', 'variant_images' => [],
        ];
    }

    private function anthropicResponse(array $draft): array
    {
        return ['content' => [['type' => 'text', 'text' => json_encode($draft)]]];
    }

    public function test_request_requires_products_manage_permission(): void
    {
        $this->postJson($this->url(), $this->textMessage('áo thun'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson($this->url(), $this->textMessage('áo thun'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_successful_call_returns_the_parsed_draft_and_raw_reply(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->anthropicResponse($this->draft()))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo sơ mi trắng giá 350k size S M L'))
            ->assertOk()
            ->assertJsonPath('data.name', 'Áo sơ mi trắng')
            ->assertJsonPath('data.sizes', ['S', 'M', 'L'])
            ->assertJsonStructure(['data', 'raw']);
        Http::assertSentCount(1);
    }

    public function test_active_category_and_brand_names_are_sent_to_the_provider(): void
    {
        Category::factory()->create(['name' => 'Áo thun', 'is_active' => true]);
        Category::factory()->create(['name' => 'Ngừng bán', 'is_active' => false]);
        Brand::factory()->create(['name' => 'Local Brand X', 'is_active' => true]);
        Http::fake(['api.anthropic.com/*' => Http::response($this->anthropicResponse($this->draft()))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))->assertOk();
        Http::assertSent(function ($request) {
            $system = $request['system'];

            return str_contains($system, 'Áo thun') && ! str_contains($system, 'Ngừng bán') && str_contains($system, 'Local Brand X');
        });
    }

    public function test_category_brand_and_variant_images_pass_through_when_valid(): void
    {
        $draft = $this->draft();
        $draft['category'] = 'Áo thun';
        $draft['brand'] = 'Local Brand X';
        $draft['variant_images'] = [['color' => 'Trắng', 'image_index' => 1]];
        Http::fake(['api.anthropic.com/*' => Http::response($this->anthropicResponse($draft))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertOk()
            ->assertJsonPath('data.category', 'Áo thun')
            ->assertJsonPath('data.brand', 'Local Brand X')
            ->assertJsonPath('data.variant_images', [['color' => 'Trắng', 'image_index' => 1]]);
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
        Http::fake(['api.anthropic.com/*' => Http::response($this->anthropicResponse($draft))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertOk()
            ->assertJsonPath('data.variant_images', [['color' => 'Trắng', 'image_index' => 1]]);
    }

    public function test_missing_fields_in_ai_reply_return_a_bad_gateway_error(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->anthropicResponse(['name' => 'Áo']))]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertStatus(502);
    }

    public function test_non_json_ai_reply_returns_a_bad_gateway_error(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'không phải JSON']]])]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertStatus(502);
    }

    public function test_connection_failure_returns_a_retryable_message(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::failedConnection()]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertStatus(503);
    }

    public function test_provider_error_status_returns_a_retryable_message(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([], 500)]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo thun'))
            ->assertStatus(503);
    }

    public function test_call_is_rate_limited(): void
    {
        config(['services.ai.requests_per_minute' => 2]);
        Http::fake(['api.anthropic.com/*' => Http::response($this->anthropicResponse($this->draft()))]);
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

    public function test_openai_compatible_provider_can_be_selected_via_config(): void
    {
        config([
            'services.ai.provider' => 'openai_compatible',
            'services.ai.openai_compatible.endpoint' => 'https://internal-ai.example.test',
            'services.ai.openai_compatible.key' => 'internal-key',
        ]);
        Http::fake(['internal-ai.example.test/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($this->draft())]]],
        ])]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->url(), $this->textMessage('áo sơ mi trắng'))
            ->assertOk()->assertJsonPath('data.name', 'Áo sơ mi trắng');
        Http::assertSent(fn ($request) => $request->url() === 'https://internal-ai.example.test/v1/chat/completions');
    }
}
