<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductAssistStreamTest extends TestCase
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
        return route('products.assist-stream');
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
        $product = Product::factory()->create(['name' => 'Áo bất kỳ']);
        $json = json_encode($this->filterReply());
        Http::fake(['internal-ai.example.test/*' => Http::response($this->sseBody([substr($json, 0, 10), substr($json, 10)]), 200)]);

        $response = $this->post($this->url(), $this->textMessage('áo thun'))->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('event: delta', $content);
        $payload = $this->eventPayload($content, 'done');
        $this->assertNotNull($payload, 'Expected a "done" event carrying the resolved payload.');
        $this->assertSame($product->id, $payload['products'][0]['id']);
        $this->assertArrayHasKey('raw', $payload);
    }

    public function test_stream_emits_an_error_event_when_the_reply_fails_to_parse(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::response($this->sseBody(['không phải JSON']), 200)]);

        $content = $this->post($this->url(), $this->textMessage('áo thun'))->assertOk()->streamedContent();

        $this->assertStringContainsString('event: error', $content);
    }

    public function test_stream_emits_an_error_event_on_connection_failure(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::failedConnection()]);

        $content = $this->post($this->url(), $this->textMessage('áo thun'))->assertOk()->streamedContent();

        $this->assertStringContainsString('event: error', $content);
    }

    public function test_stream_route_is_guest_callable_and_rate_limited_together_with_the_json_route(): void
    {
        config(['services.ai.shopping_assist_requests_per_minute' => 1]);
        Http::fake(['internal-ai.example.test/*' => Http::response($this->sseBody([json_encode($this->filterReply())]), 200)]);

        $this->post($this->url(), $this->textMessage('áo thun'))->assertOk();
        $this->post($this->url(), $this->textMessage('áo thun'))->assertStatus(429);
    }
}
