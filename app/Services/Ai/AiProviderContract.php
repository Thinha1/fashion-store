<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;

/**
 * A backend that can turn a normalized chat history into a text completion.
 *
 * Implementations own two concerns only: building the provider-specific
 * request payload from the normalized `$messages`, and extracting the raw
 * text reply from the provider-specific response. Prompt content and
 * response parsing (JSON draft shape) live outside this contract so that
 * switching provider never touches them.
 */
interface AiProviderContract
{
    /**
     * @param  array<int, array{role: string, content: array<int, array<string, mixed>>}>  $messages
     *                                                                                                Normalized chat history — each content block is either
     *                                                                                                `['type' => 'text', 'text' => string]` or
     *                                                                                                `['type' => 'image', 'media_type' => string, 'data' => string]`
     *                                                                                                (base64, no data-URL prefix).
     *
     * @throws ConnectionException
     */
    public function complete(array $messages, string $systemPrompt): string;
}
