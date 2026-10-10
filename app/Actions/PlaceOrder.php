<?php

namespace App\Actions;

use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\CartException;
use App\Services\Cart\InsufficientStockException;
use App\Services\Cart\ShoppingCart;
use App\Services\Pricing\InvalidCouponException;
use App\Services\Pricing\LinePrice;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turn the customer's active cart into an order, in one transaction and in
 * the order fixed by BUSINESS_FLOWS.md §3:
 *  - lock the cart (a double-submitted checkout can't place two orders);
 *  - lock every variant row FIRST, then check it is still on sale and in stock;
 *  - reprice every line server-side and check/apply the coupon (row-locked);
 *  - snapshot the order and its lines, take stock, count the coupon use;
 *  - mark the cart `converted` and write an audit log with stock before/after.
 * Any failure rolls the whole thing back.
 */
class PlaceOrder
{
    public function __construct(
        private PriceCalculator $prices,
        private ShoppingCart $cart,
    ) {}

    /**
     * @param  array{payment_method: string, coupon_code?: ?string, customer_note?: ?string}  $input
     *
     * @throws CartException when the cart is empty or a line can't be fulfilled
     * @throws InvalidCouponException
     */
    public function execute(User $customer, Address $address, array $input): Order
    {
        return DB::transaction(function () use ($customer, $address, $input): Order {
            $cart = Cart::query()
                ->where('user_id', $customer->id)
                ->where('status', 'active')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $items = $cart?->items()->orderBy('product_variant_id')->get() ?? collect();

            if ($items->isEmpty()) {
                throw new CartException('Giỏ hàng của bạn đang trống.');
            }

            $variants = $this->lockVariants($items);
            $this->assertFulfillable($items, $variants);

            $lines = $items->map(fn (CartItem $item): LinePrice => $this->prices->priceVariant(
                $variants[$item->product_variant_id],
                $item->quantity,
            ));

            $couponCode = trim((string) ($input['coupon_code'] ?? ''));
            $coupon = $couponCode === '' ? null : $this->prices->findCoupon($couponCode, lockForUpdate: true);
            $quote = $this->prices->quote($lines, $coupon, $customer);

            $order = Order::query()->create([
                'order_number' => $this->newOrderNumber(),
                'user_id' => $customer->id,
                'discount_id' => $coupon?->id,
                'status' => 'pending',
                'status_history' => [[
                    'from' => null,
                    'to' => 'pending',
                    'actor_id' => $customer->id,
                    'note' => 'Khách đặt hàng',
                    'at' => now()->toIso8601String(),
                ]],
                'payment_method' => $input['payment_method'],
                'payment_status' => 'unpaid',
                'customer_name' => $address->recipient_name,
                'customer_email' => $customer->email,
                'customer_phone' => $address->phone,
                'province_name' => $address->province_name,
                'district_name' => $address->district_name,
                'ward_name' => $address->ward_name,
                'shipping_address' => $address->address_line,
                'customer_note' => $input['customer_note'] ?? null,
                'subtotal' => $quote->subtotal,
                'discount_amount' => $quote->discountAmount,
                'shipping_fee' => $quote->shippingFee,
                'grand_total' => $quote->grandTotal,
                'placed_at' => now(),
            ]);

            $stockChanges = [];

            foreach ($lines as $line) {
                $variant = $line->variant;

                $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'discount_id' => $line->discount?->id,
                    'product_name' => $variant->product->name,
                    'sku' => $variant->sku,
                    'size_name' => $variant->size,
                    'color_name' => $variant->color,
                    'original_unit_price' => $line->originalUnitPrice,
                    'discount_amount' => $line->lineDiscountAmount(),
                    'unit_price' => $line->unitPrice,
                    'quantity' => $line->quantity,
                    'line_total' => $line->lineTotal(),
                ]);

                $stockChanges[] = [
                    'variant_id' => $variant->id,
                    'quantity' => $line->quantity,
                    'stock_before' => $variant->stock_quantity,
                    'stock_after' => $variant->stock_quantity - $line->quantity,
                ];

                $variant->stock_quantity -= $line->quantity;
                $variant->save();
            }

            $coupon?->increment('used_count');
            $cart->update(['status' => 'converted']);

            AuditLog::query()->create([
                'actor_id' => $customer->id,
                'action' => 'order.placed',
                'subject_type' => $order->getMorphClass(),
                'subject_id' => $order->id,
                'old_values' => null,
                'new_values' => [
                    'order_number' => $order->order_number,
                    'grand_total' => $quote->grandTotal,
                    'coupon_id' => $coupon?->id,
                    'stock' => $stockChanges,
                ],
            ]);

            return $order;
        });
    }

    /**
     * Lock in id order so two checkouts sharing variants can't deadlock.
     *
     * @param  Collection<int, CartItem>  $items
     * @return Collection<int, ProductVariant>
     */
    private function lockVariants(Collection $items): Collection
    {
        $variants = ProductVariant::withTrashed()
            ->whereIn('id', $items->pluck('product_variant_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $variants->load(['product' => fn ($query) => $query->withTrashed(), 'activeDiscounts']);

        return $variants;
    }

    /**
     * @param  Collection<int, CartItem>  $items
     * @param  Collection<int, ProductVariant>  $variants
     *
     * @throws InsufficientStockException
     */
    private function assertFulfillable(Collection $items, Collection $variants): void
    {
        $problems = [];

        foreach ($items as $item) {
            $variant = $variants->get($item->product_variant_id);
            $name = $variant?->product ? "{$variant->product->name} ({$variant->color}, {$variant->size})" : 'Một sản phẩm';

            if (! $this->cart->isPurchasable($variant)) {
                $problems[] = "{$name} đã ngừng bán.";
            } elseif ($item->quantity > $variant->stock_quantity) {
                $problems[] = $variant->stock_quantity > 0
                    ? "{$name} chỉ còn {$variant->stock_quantity} sản phẩm."
                    : "{$name} đã hết hàng.";
            }
        }

        if ($problems !== []) {
            throw InsufficientStockException::forLines($problems);
        }
    }

    /**
     * "DH" + yymmdd + 6 random characters, letters and digits only so a bank
     * transfer memo can carry it unchanged (banks strip punctuation).
     */
    private function newOrderNumber(): string
    {
        do {
            $number = 'DH'.now()->format('ymd').Str::upper(Str::random(6));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
