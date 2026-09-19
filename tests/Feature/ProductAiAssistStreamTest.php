<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductAiAssistStreamTest extends TestCase
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
        return route('admin.products.ai-assist-stream');
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

    /**
     * Simulates an upstream OpenAI-compatible streaming reply: the draft
     * JSON split across a few `delta.content` chunks (proving the parser
     * reassembles it correctly), terminated the standard way.
     *
     * @param  array<int, string>  $chunks
     */
    private function sseBody(array $chunks): string
    {
        $frames = array_map(
            fn (string $chunk) => 'data: '.json_encode(['choices' => [['delta' => ['content' => $chunk]]]])."\n\n",
            $chunks,
        );

        return implode('', $frames)."data: [DONE]\n\n";
    }

    /**
     * Finds the `data: ...` payload of the first SSE frame for the given
     * event name — frames are separated by a blank line, exactly as `emit()`
     * in ProductAiAssistStreamController writes them.
     *
     * @return array<string, mixed>|null
     */
    private function eventPayload(string $content, string $event): ?array
    {
        foreach (explode("\n\n", trim($content)) as $frame) {
            if (! str_starts_with($frame, "event: {$event}\ndata: ")) {
                continue;
            }

            return json_decode(substr($frame, strlen("event: {$event}\ndata: ")), true);
        }

        return null;
    }

    public function test_stream_forwards_deltas_and_ends_with_the_same_resolved_payload_as_the_json_endpoint(): void
    {
        $json = json_encode($this->draft());
        $chunks = [substr($json, 0, 10), substr($json, 10)];
        Http::fake(['internal-ai.example.test/*' => Http::response($this->sseBody($chunks), 200, ['Content-Type' => 'text/event-stream'])]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->post($this->url(), $this->textMessage('áo sơ mi trắng giá 350k'))
            ->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString('event: delta', $content);

        $payload = $this->eventPayload($content, 'done');
        $this->assertNotNull($payload, 'Expected a "done" event carrying the resolved payload.');
        $this->assertSame('Áo sơ mi trắng', $payload['data']['name']);
        $this->assertSame(['S', 'M', 'L'], $payload['data']['sizes']);
        $this->assertArrayHasKey('raw', $payload);
    }

    public function test_stream_emits_an_error_event_when_the_reply_fails_to_parse(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::response($this->sseBody(['không phải JSON']), 200)]);

        $content = $this->actingAs(User::factory()->admin()->create())
            ->post($this->url(), $this->textMessage('áo thun'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('event: error', $content);
    }

    public function test_stream_emits_an_error_event_on_connection_failure(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::failedConnection()]);

        $content = $this->actingAs(User::factory()->admin()->create())
            ->post($this->url(), $this->textMessage('áo thun'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('event: error', $content);
    }

    public function test_stream_route_requires_products_manage_permission(): void
    {
        $this->postJson($this->url(), $this->textMessage('áo thun'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson($this->url(), $this->textMessage('áo thun'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_stream_route_is_rate_limited_together_with_the_json_route(): void
    {
        config(['services.ai.requests_per_minute' => 1]);
        Http::fake(['internal-ai.example.test/*' => Http::response($this->sseBody([json_encode($this->draft())]), 200)]);
        $this->actingAs(User::factory()->admin()->create());
        $this->post($this->url(), $this->textMessage('áo thun'))->assertOk();
        $this->post($this->url(), $this->textMessage('áo thun'))->assertStatus(429);
    }
}
