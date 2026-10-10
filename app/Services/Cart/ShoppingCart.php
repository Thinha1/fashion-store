<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cart rules shared by the cart page and checkout. A customer has at most one
 * `active` cart; prices are never stored on cart items, only recomputed.
 * Stock checks here are a courtesy — PlaceOrder re-checks under row locks.
 */
class ShoppingCart
{
    public const MAX_LINE_QUANTITY = 99;

    public function __construct(private PriceCalculator $prices) {}

    /**
     * Add a variant, merging with the line already holding it.
     *
     * @throws CartException
     */
    public function add(User $user, ProductVariant $variant, int $quantity): CartItem
    {
        $this->assertPurchasable($variant);

        return DB::transaction(function () use ($user, $variant, $quantity): CartItem {
            $cart = $user->activeCart()->first() ?? $user->carts()->create(['status' => 'active']);

            $item = $cart->items()
                ->where('product_variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            $newQuantity = ($item->quantity ?? 0) + $quantity;
            $this->assertQuantityAvailable($variant, $newQuantity);

            if ($item) {
                $item->update(['quantity' => $newQuantity]);

                return $item;
            }

            return $cart->items()->create([
                'product_variant_id' => $variant->id,
                'quantity' => $newQuantity,
            ]);
        });
    }

    /**
     * @throws CartException
     */
    public function updateQuantity(CartItem $item, int $quantity): void
    {
        $variant = $item->productVariant()->withTrashed()->with(['product' => fn ($query) => $query->withTrashed()])->first();

        $this->assertPurchasable($variant);
        $this->assertQuantityAvailable($variant, $quantity);

        $item->update(['quantity' => $quantity]);
    }

    /**
     * Every line of the cart with its live price and any problem — including
     * variants removed from sale after they were added.
     *
     * @return Collection<int, CartLine>
     */
    public function lines(?Cart $cart): Collection
    {
        if (! $cart) {
            return collect();
        }

        $cart->load([
            'items' => fn ($query) => $query->oldest('id'),
            'items.productVariant' => fn ($query) => $query->withTrashed(),
            'items.productVariant.product' => fn ($query) => $query->withTrashed(),
            'items.productVariant.product.images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order'),
            'items.productVariant.activeDiscounts',
        ]);

        return $cart->items->map(fn (CartItem $item): CartLine => new CartLine(
            item: $item,
            price: $this->prices->priceVariant($item->productVariant, $item->quantity),
            problem: $this->problemWith($item),
        ));
    }

    /**
     * Total units in the customer's active cart (header badge).
     */
    public function itemCount(User $user): int
    {
        return (int) CartItem::query()
            ->whereHas('cart', fn ($query) => $query->where('user_id', $user->id)->where('status', 'active'))
            ->sum('quantity');
    }

    private function problemWith(CartItem $item): ?string
    {
        $variant = $item->productVariant;

        if (! $this->isPurchasable($variant)) {
            return 'Sản phẩm đã ngừng bán.';
        }

        if ($variant->stock_quantity <= 0) {
            return 'Sản phẩm đã hết hàng.';
        }

        if ($item->quantity > $variant->stock_quantity) {
            return "Chỉ còn {$variant->stock_quantity} sản phẩm, hãy giảm số lượng.";
        }

        return null;
    }

    private function isPurchasable(?ProductVariant $variant): bool
    {
        return $variant !== null
            && ! $variant->trashed()
            && $variant->is_active
            && $variant->product !== null
            && ! $variant->product->trashed()
            && $variant->product->status === 'active';
    }

    /**
     * @throws CartException
     */
    private function assertPurchasable(?ProductVariant $variant): void
    {
        if (! $this->isPurchasable($variant)) {
            throw new CartException('Sản phẩm này đã ngừng bán.');
        }
    }

    /**
     * @throws CartException
     */
    private function assertQuantityAvailable(ProductVariant $variant, int $quantity): void
    {
        if ($variant->stock_quantity <= 0) {
            throw new CartException('Sản phẩm này đã hết hàng.');
        }

        if ($quantity > $variant->stock_quantity) {
            throw new CartException("Chỉ còn {$variant->stock_quantity} sản phẩm cho lựa chọn này.");
        }

        if ($quantity > self::MAX_LINE_QUANTITY) {
            throw new CartException('Mỗi sản phẩm chỉ đặt tối đa '.self::MAX_LINE_QUANTITY.' cái.');
        }
    }
}
