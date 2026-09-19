<?php

namespace App\Services\Ai;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

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

    /**
     * Same call, but streamed: `$onDelta` is invoked once per incremental
     * chunk of text as the provider produces it (for a live "typing"
     * status in the UI — see ProductAiAssistStreamController), while the
     * full accumulated text is still returned at the end exactly like
     * `complete()` — callers that don't care about the incremental delta
     * can treat this as a drop-in replacement.
     *
     * @param  array<int, array{role: string, content: array<int, array<string, mixed>>}>  $messages
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function completeStreamed(array $messages, string $systemPrompt, Closure $onDelta): string;
}
