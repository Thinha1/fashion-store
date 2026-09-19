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
use App\Services\Ai\ShoppingAssistRecommender;
use App\Services\Ai\ShoppingAssistResponseParser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The customer-facing shopping-assist widget: turns a free-text request into
 * a search filter (see ShoppingAssistPromptBuilder), then looks up real
 * products server-side (ShoppingAssistRecommender) — the AI itself never
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
        ShoppingAssistResponseParser $responseParser,
        ShoppingAssistRecommender $recommender,
    ): JsonResponse {
        $messages = $request->validated('messages');

        // Same reasoning as the admin assistant: the model can only pick a
        // category that actually exists, by being shown the real list.
        $categories = Category::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();

        $wireMessages = array_map(fn (array $message): array => [
            'role' => $message['role'],
            'content' => [['type' => 'text', 'text' => $message['content']]],
        ], $messages);

        try {
            $reply = $provider->complete($wireMessages, $promptBuilder->build($categories, ProductVariant::SIZES));
        } catch (ConnectionException|RuntimeException) {
            abort(503, self::UNAVAILABLE);
        }

        try {
            $filter = $responseParser->parse($reply);
        } catch (InvalidAiResponseException $exception) {
            Log::warning('Shopping assist: reply failed to parse.', [
                'reason' => $exception->getMessage(),
                'raw' => Str::limit($reply, 2000),
            ]);
            abort(502, 'AI trả về nội dung không đúng định dạng. Vui lòng thử lại hoặc diễn đạt lại yêu cầu.');
        }

        $products = $recommender->search($filter);

        // The model's own "reply" only ever sets the tone (see the prompt) —
        // whether anything was actually found, and how many, is always
        // reported by the server from the real query result, never trusted
        // from the model's own words.
        $resultLine = $products->isEmpty()
            ? 'Mình chưa tìm thấy sản phẩm nào khớp, bạn thử đổi mức giá hoặc size xem sao nhé.'
            : "Mình tìm được {$products->count()} sản phẩm phù hợp cho bạn:";

        return response()->json([
            'reply' => trim($filter['reply']." \n".$resultLine),
            'products' => $products->map($this->toCard(...))->all(),
            'raw' => $reply,
        ]);
    }

    /**
     * @return array{id: int, name: string, brand: ?string, price: string, image_url: ?string, in_stock: bool, url: string}
     */
    private function toCard(Product $product): array
    {
        $image = $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand?->name,
            'price' => number_format((float) $product->base_price, 0, ',', '.').' ₫',
            'image_url' => $image ? Storage::disk(config('filesystems.image_disk'))->url($image->path) : null,
            'in_stock' => (int) $product->stock_total > 0,
            'url' => route('products.show', $product),
        ];
    }
}
