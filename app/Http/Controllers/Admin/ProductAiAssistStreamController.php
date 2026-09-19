<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductAiAssistRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Services\Ai\AdminPageDirectory;
use App\Services\Ai\AiProviderContract;
use App\Services\Ai\InvalidAiResponseException;
use App\Services\Ai\ProductDraftPromptBuilder;
use App\Services\Ai\ProductDraftResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Same call as ProductAiAssistController, but forwards the AI's output as it
 * streams in (Server-Sent Events) instead of waiting for the whole reply —
 * so the widget can show live progress instead of a blank "thinking" bubble
 * for as long as a self-hosted model takes. The final "done" event carries
 * the exact same {data, navigate, raw} shape the JSON endpoint returns, both
 * built by the same ProductDraftResolver — this controller only changes the
 * transport, never the parsing/validation, and the JSON endpoint/route is
 * left untouched as the widget's fallback.
 */
class ProductAiAssistStreamController extends Controller
{
    private const UNAVAILABLE = 'Chưa thể tạo nội dung gợi ý lúc này. Vui lòng thử lại sau hoặc nhập thông tin thủ công.';

    public function __invoke(
        ProductAiAssistRequest $request,
        AiProviderContract $provider,
        ProductDraftPromptBuilder $promptBuilder,
        ProductDraftResolver $resolver,
        AdminPageDirectory $pages,
    ): StreamedResponse {
        $messages = $request->validated('messages');
        $categories = Category::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();
        $brands = Brand::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();
        $systemPrompt = $promptBuilder->build($categories, $brands, $pages->describeForPrompt());

        return response()->stream(function () use ($provider, $messages, $systemPrompt, $resolver): void {
            try {
                $reply = $provider->completeStreamed(
                    $messages,
                    $systemPrompt,
                    fn (string $chunk) => $this->emit('delta', ['text' => $chunk]),
                );
            } catch (ConnectionException|RuntimeException) {
                $this->emit('error', ['message' => self::UNAVAILABLE]);

                return;
            }

            try {
                $payload = $resolver->resolve($reply);
            } catch (InvalidAiResponseException $exception) {
                // Same diagnostic need as the JSON endpoint — see
                // ProductAiAssistController for why the raw reply is logged.
                Log::warning('Product AI assist (stream): reply failed to parse.', [
                    'reason' => $exception->getMessage(),
                    'raw' => Str::limit($reply, 2000),
                ]);
                $this->emit('error', ['message' => 'AI trả về nội dung không đúng định dạng. Vui lòng thử lại hoặc diễn đạt lại yêu cầu.']);

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

        echo "event: {$event}\n";
        echo 'data: '.json_encode($data)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
