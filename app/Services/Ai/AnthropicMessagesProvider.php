<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Calls the Anthropic Messages API (https://api.anthropic.com/v1/messages).
 */
class AnthropicMessagesProvider implements AiProviderContract
{
    public function complete(array $messages, string $systemPrompt): string
    {
        $response = Http::withHeaders([
            'x-api-key' => config('services.ai.anthropic.key'),
            'anthropic-version' => config('services.ai.anthropic.version'),
        ])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.ai.anthropic.model'),
                'system' => $systemPrompt,
                'max_tokens' => config('services.ai.max_tokens'),
                'messages' => array_map($this->toAnthropicMessage(...), $messages),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic Messages API request failed with status '.$response->status());
        }

        $text = $response->json('content.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Anthropic Messages API returned no text content.');
        }

        return $text;
    }

    /**
     * @param  array{role: string, content: array<int, array<string, mixed>>}  $message
     * @return array{role: string, content: array<int, array<string, mixed>>}
     */
    private function toAnthropicMessage(array $message): array
    {
        return [
            'role' => $message['role'],
            'content' => array_map($this->toAnthropicBlock(...), $message['content']),
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function toAnthropicBlock(array $block): array
    {
        if ($block['type'] === 'image') {
            return [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $block['media_type'],
                    'data' => $block['data'],
                ],
            ];
        }

        return ['type' => 'text', 'text' => $block['text']];
    }
}
