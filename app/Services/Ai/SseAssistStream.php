<?php

namespace App\Services\Ai;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared SSE plumbing for the admin and storefront AI-assist "stream"
 * controllers: both call an AiProviderContract, forward its streamed deltas
 * as `delta` events, resolve the finished reply into a payload (each domain
 * supplies its own resolver via `$resolve`), and emit the result as a `done`
 * event — or an `error` event if either step fails. Only the transport is
 * shared; what a resolved payload means is entirely up to the caller.
 */
class SseAssistStream
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  Closure(string): array<string, mixed>  $resolve
     */
    public function respond(
        AiProviderContract $provider,
        array $messages,
        string $systemPrompt,
        Closure $resolve,
        string $unavailableMessage,
        string $parseErrorLogMessage,
        string $parseErrorMessage,
    ): StreamedResponse {
        return response()->stream(function () use (
            $provider, $messages, $systemPrompt, $resolve, $unavailableMessage, $parseErrorLogMessage, $parseErrorMessage,
        ): void {
            try {
                $reply = $provider->completeStreamed(
                    $messages,
                    $systemPrompt,
                    fn (string $chunk) => $this->emit('delta', ['text' => $chunk]),
                );
            } catch (ConnectionException|RuntimeException) {
                $this->emit('error', ['message' => $unavailableMessage]);

                return;
            }

            try {
                $payload = $resolve($reply);
            } catch (InvalidAiResponseException $exception) {
                Log::warning($parseErrorLogMessage, [
                    'reason' => $exception->getMessage(),
                    'raw' => Str::limit($reply, 2000),
                ]);
                $this->emit('error', ['message' => $parseErrorMessage]);

                return;
            }

            $this->emit('done', $payload);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            // Disables buffering on nginx (a bare Content-Type header isn't
            // enough) — without it, every SSE frame would sit in nginx's
            // proxy buffer until it filled up instead of reaching the
            // browser as it's produced, defeating the point of streaming.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function emit(string $event, array $data): void
    {
        // The browser tab closing mid-stream is the only realistic way this
        // gets called after the client is gone — best-effort no-op rather
        // than throwing, since there's nothing left to report a failure to.
        if (connection_aborted()) {
            return;
        }

        // json_encode() can return false (e.g. invalid UTF-8 slipping in
        // from somewhere upstream) — echoing that literal "false" as the
        // frame body would silently hand the client a bogus payload instead
        // of a clear failure, so this substitutes invalid bytes rather than
        // ever letting encoding fail outright.
        $json = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE) ?: json_encode(['message' => 'Không thể tạo phản hồi.']);

        echo "event: {$event}\n";
        echo "data: {$json}\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
