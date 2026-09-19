<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ProductAssistRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Ai\AiProviderContract;
use App\Services\Ai\InvalidAiResponseException;
use App\Services\Ai\ShoppingAssistPromptBuilder;
use App\Services\Ai\ShoppingAssistResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Same call as ProductAssistController, but forwards the AI's output as it
 * streams in (Server-Sent Events) instead of waiting for the whole reply —
 * so the widget can show live progress instead of a blank "thinking" bubble
 * for as long as a self-hosted model takes. The final "done" event carries
 * the exact same {reply, products, raw} shape the JSON endpoint returns,
 * both built by the same ShoppingAssistResolver — this controller only
 * changes the transport, never the parsing/lookup logic, and the JSON
 * endpoint/route is left untouched as the widget's fallback.
 */
class ProductAssistStreamController extends Controller
{
    private const UNAVAILABLE = 'Chưa thể gợi ý lúc này. Vui lòng thử lại sau hoặc tự tìm trong danh mục sản phẩm.';

    public function __invoke(
        ProductAssistRequest $request,
        AiProviderContract $provider,
        ShoppingAssistPromptBuilder $promptBuilder,
        ShoppingAssistResolver $resolver,
    ): StreamedResponse {
        $messages = $request->validated('messages');
        $categories = Category::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();
        $systemPrompt = $promptBuilder->build($categories, ProductVariant::SIZES, $this->currentProductContext($request));

        $wireMessages = array_map(fn (array $message): array => [
            'role' => $message['role'],
            'content' => [['type' => 'text', 'text' => $message['content']]],
        ], $messages);

        return response()->stream(function () use ($provider, $wireMessages, $systemPrompt, $resolver): void {
            try {
                $reply = $provider->completeStreamed(
                    $wireMessages,
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
                Log::warning('Shopping assist (stream): reply failed to parse.', [
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
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function currentProductContext(ProductAssistRequest $request): string
    {
        $productId = $request->validated('context_product_id');

        if (! $productId) {
            return '';
        }

        $product = Product::query()->where('status', 'active')->with('category')->find($productId);

        if (! $product) {
            return '';
        }

        return sprintf(
            '%s (danh mục: %s, giá: %s đ)',
            $product->name,
            $product->category?->name ?? 'chưa phân loại',
            number_format((float) $product->base_price, 0, ',', '.'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function emit(string $event, array $data): void
    {
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
