<?php

namespace App\Services\Ai;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a raw AI reply into the exact payload shape both the JSON and
 * streaming shopping-assist endpoints send to the browser — kept in one
 * place so the two transports (see ProductAssistController and
 * ProductAssistStreamController) never diverge on parsing/lookup/wording.
 */
class ShoppingAssistResolver
{
    public function __construct(
        private readonly ShoppingAssistResponseParser $responseParser,
        private readonly ShoppingAssistRecommender $recommender,
    ) {}

    /**
     * @return array{reply: string, products: array<int, array<string, mixed>>, raw: string}
     *
     * @throws InvalidAiResponseException
     */
    public function resolve(string $reply): array
    {
        $filter = $this->responseParser->parse($reply);

        $products = $this->recommender->search($filter);
        $isFallback = false;

        // An exact match came up empty — rather than a dead-end "not found",
        // suggest something close (same category, or just popular items)
        // so the conversation has somewhere to go. Still real, active
        // products from the DB, never anything the AI itself picked.
        if ($products->isEmpty()) {
            $products = $this->recommender->fallback($filter);
            $isFallback = $products->isNotEmpty();
        }

        // The model's own "reply" only ever sets the tone (see the prompt) —
        // whether anything was actually found, and how many, is always
        // reported by the server from the real query result, never trusted
        // from the model's own words.
        $resultLine = match (true) {
            $products->isEmpty() => 'Mình chưa tìm thấy sản phẩm nào phù hợp, bạn thử mô tả khác xem sao nhé.',
            $isFallback => 'Mình chưa tìm thấy đúng ý bạn, nhưng có thể bạn sẽ thích những sản phẩm này:',
            default => "Mình tìm được {$products->count()} sản phẩm phù hợp cho bạn:",
        };

        return [
            'reply' => trim($filter['reply']." \n".$resultLine),
            'products' => $products->map($this->toCard(...))->all(),
            'raw' => $reply,
        ];
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
