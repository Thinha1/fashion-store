<?php

namespace App\Services\Ai;

use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Calls a self-hosted, OpenAI-compatible chat completions endpoint —
 * for a company that wants to keep product photos/text on its own
 * infrastructure instead of an external AI vendor.
 */
class OpenAiCompatibleProvider implements AiProviderContract
{
    public function __construct(private readonly AiSettings $settings) {}

    public function complete(array $messages, string $systemPrompt): string
    {
        $response = $this->newRequest()->post($this->endpoint().'/chat/completions', $this->payload($messages, $systemPrompt));

        if ($response->failed()) {
            throw new RuntimeException('Self-hosted chat completions request failed with status '.$response->status());
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Self-hosted chat completions returned no text content.');
        }

        return $text;
    }

    public function completeStreamed(array $messages, string $systemPrompt, Closure $onDelta): string
    {
        $response = $this->newRequest()
            ->withOptions(['stream' => true])
            ->post($this->endpoint().'/chat/completions', [...$this->payload($messages, $systemPrompt), 'stream' => true]);

        if ($response->failed()) {
            throw new RuntimeException('Self-hosted chat completions request failed with status '.$response->status());
        }

        $body = $response->toPsrResponse()->getBody();
        $text = '';
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($newlinePosition = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newlinePosition));
                $buffer = substr($buffer, $newlinePosition + 1);

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $data = trim(substr($line, strlen('data:')));

                if ($data === '[DONE]') {
                    break 2;
                }

                $delta = json_decode($data, true)['choices'][0]['delta']['content'] ?? null;

                if (is_string($delta) && $delta !== '') {
                    $text .= $delta;
                    $onDelta($delta);
                }
            }
        }

        if (trim($text) === '') {
            throw new RuntimeException('Self-hosted chat completions returned no text content.');
        }

        return $text;
    }

    private function newRequest(): PendingRequest
    {
        return Http::withToken($this->settings->apiKey())
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(60);
    }

    /**
     * `endpoint` is the full base URL exactly as the provider gives it to
     * copy-paste — for most OpenAI-compatible servers that already ends
     * in "/v1", so only "/chat/completions" is appended by callers, never a
     * hardcoded "/v1" (that would double it for the common case).
     */
    private function endpoint(): string
    {
        return rtrim($this->settings->endpoint(), '/');
    }

    /**
     * @param  array<int, array{role: string, content: array<int, array<string, mixed>>}>  $messages
     * @return array<string, mixed>
     */
    private function payload(array $messages, string $systemPrompt): array
    {
        return [
            'model' => $this->settings->model(),
            'max_tokens' => $this->settings->maxTokens(),
            // Hard constraint at the API level so a reply isn't malformed
            // JSON just because the model drifted from the prompt's
            // instructions — most OpenAI-compatible servers support or
            // safely ignore this field. ProductDraftResponseParser's own
            // ```json fence tolerance stays as the safety net either way.
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ...array_map($this->toOpenAiMessage(...), $messages),
            ],
        ];
    }

    /**
     * @param  array{role: string, content: array<int, array<string, mixed>>}  $message
     * @return array{role: string, content: array<int, array<string, mixed>>}
     */
    private function toOpenAiMessage(array $message): array
    {
        return [
            'role' => $message['role'],
            'content' => array_map($this->toOpenAiBlock(...), $message['content']),
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function toOpenAiBlock(array $block): array
    {
        if ($block['type'] === 'image') {
            return [
                'type' => 'image_url',
                'image_url' => ['url' => 'data:'.$block['media_type'].';base64,'.$block['data']],
            ];
        }

        return ['type' => 'text', 'text' => $block['text']];
    }
}
