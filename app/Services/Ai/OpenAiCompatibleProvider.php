<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Calls a self-hosted, OpenAI-compatible chat completions endpoint —
 * for a company that wants to keep product photos/text on its own
 * infrastructure instead of an external AI vendor.
 */
class OpenAiCompatibleProvider implements AiProviderContract
{
    public function complete(array $messages, string $systemPrompt): string
    {
        // `endpoint` is the full base URL exactly as the provider gives it to
        // copy-paste — for most OpenAI-compatible servers that already ends
        // in "/v1", so only "/chat/completions" is appended here, never a
        // hardcoded "/v1" (that would double it for the common case).
        $endpoint = rtrim((string) config('services.ai.openai_compatible.endpoint'), '/');

        $response = Http::withToken((string) config('services.ai.openai_compatible.key'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(60)
            ->post($endpoint.'/chat/completions', [
                'model' => config('services.ai.openai_compatible.model'),
                'max_tokens' => config('services.ai.max_tokens'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ...array_map($this->toOpenAiMessage(...), $messages),
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Self-hosted chat completions request failed with status '.$response->status());
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Self-hosted chat completions returned no text content.');
        }

        return $text;
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
