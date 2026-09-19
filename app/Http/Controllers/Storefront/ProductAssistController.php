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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The customer-facing shopping-assist widget: turns a free-text request into
 * a search filter (see ShoppingAssistPromptBuilder), then looks up real
 * products server-side (see ShoppingAssistResolver) — the AI itself never
 * names or picks a product, so it can never hand the customer a
 * hallucinated one. Guest-callable by design: no permission/auth gate,
 * mirroring the rest of the storefront's public browsing routes.
 */
class ProductAssistController extends Controller
{
    private const UNAVAILABLE = 'Chưa thể gợi ý lúc này. Vui lòng thử lại sau hoặc tự tìm trong danh mục sản phẩm.';

    public function __invoke(
        ProductAssistRequest $request,
        AiProviderContract $provider,
        ShoppingAssistPromptBuilder $promptBuilder,
        ShoppingAssistResolver $resolver,
    ): JsonResponse {
        $messages = $request->validated('messages');

        // Same reasoning as the admin assistant: the model can only pick a
        // category that actually exists, by being shown the real list.
        $categories = Category::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();
        $systemPrompt = $promptBuilder->build($categories, ProductVariant::SIZES, $this->currentProductContext($request));

        $wireMessages = array_map(fn (array $message): array => [
            'role' => $message['role'],
            'content' => [['type' => 'text', 'text' => $message['content']]],
        ], $messages);

        try {
            $reply = $provider->complete($wireMessages, $systemPrompt);
        } catch (ConnectionException|RuntimeException) {
            abort(503, self::UNAVAILABLE);
        }

        try {
            $payload = $resolver->resolve($reply);
        } catch (InvalidAiResponseException $exception) {
            Log::warning('Shopping assist: reply failed to parse.', [
                'reason' => $exception->getMessage(),
                'raw' => Str::limit($reply, 2000),
            ]);
            abort(502, 'AI trả về nội dung không đúng định dạng. Vui lòng thử lại hoặc diễn đạt lại yêu cầu.');
        }

        return response()->json($payload);
    }

    /**
     * A short "khách đang xem: ..." line built from a real, active product —
     * only ever the widget's own current-page id (see the `data-current-
     * product-id` attribute in layouts/app.blade.php), never anything the
     * model itself supplies, so referencing it back to the model is safe.
     */
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
}
