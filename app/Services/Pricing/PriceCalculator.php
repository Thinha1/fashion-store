<?php

namespace App\Services\Pricing;

use App\Models\Discount;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The single place prices are computed (BUSINESS_FLOWS.md §4). Catalog, cart,
 * checkout and PlaceOrder all call this instead of doing their own math, and
 * nothing here trusts a price sent by the client.
 *
 * Order of application: the best active variant discount (scope=variant)
 * lowers each line's unit price first; a coupon (scope=order) then lowers the
 * subtotal; the fixed shipping fee is added last. All amounts are whole VND.
 */
class PriceCalculator
{
    /**
     * Price one variant line. Uses the variant's `activeDiscounts` relation if
     * it is already eager-loaded (avoid N+1 on lists), otherwise queries it.
     */
    public function priceVariant(ProductVariant $variant, int $quantity = 1): LinePrice
    {
        $originalUnitPrice = $this->originalUnitPrice($variant);
        $bestDiscount = null;
        $bestAmount = 0;

        foreach ($this->activeDiscountsFor($variant) as $discount) {
            $amount = $this->discountAmount($discount, $originalUnitPrice);

            if ($amount > $bestAmount) {
                $bestDiscount = $discount;
                $bestAmount = $amount;
            }
        }

        return new LinePrice(
            variant: $variant,
            quantity: $quantity,
            originalUnitPrice: $originalUnitPrice,
            unitDiscountAmount: $bestAmount,
            unitPrice: $originalUnitPrice - $bestAmount,
            discount: $bestDiscount,
        );
    }

    /**
     * Variant price, falling back to the product's base price.
     */
    public function originalUnitPrice(ProductVariant $variant): int
    {
        return $this->toMoney($variant->price ?? $variant->product->base_price);
    }

    /**
     * Totals for already-priced lines, optionally with a coupon.
     *
     * @param  Collection<int, LinePrice>  $lines
     *
     * @throws InvalidCouponException
     */
    public function quote(Collection $lines, ?Discount $coupon = null, ?User $customer = null): OrderQuote
    {
        $subtotal = (int) $lines->sum(fn (LinePrice $line): int => $line->lineTotal());
        $discountAmount = $coupon ? $this->couponDiscount($coupon, $subtotal, $customer) : 0;
        $shippingFee = $lines->isEmpty() ? 0 : $this->toMoney(config('store.shipping_fee'));

        return new OrderQuote(
            lines: $lines,
            subtotal: $subtotal,
            discountAmount: $discountAmount,
            shippingFee: $shippingFee,
            grandTotal: $subtotal - $discountAmount + $shippingFee,
            coupon: $coupon,
        );
    }

    /**
     * Look up a coupon by the code the customer typed (case-insensitive).
     *
     * @throws InvalidCouponException
     */
    public function findCoupon(string $code, bool $lockForUpdate = false): Discount
    {
        $coupon = Discount::query()
            ->where('scope', 'order')
            ->where('code', mb_strtoupper(trim($code)))
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first();

        if (! $coupon) {
            throw new InvalidCouponException('Mã giảm giá không tồn tại.');
        }

        return $coupon;
    }

    /**
     * How much the coupon takes off this subtotal, after checking every rule.
     *
     * @throws InvalidCouponException
     */
    public function couponDiscount(Discount $coupon, int $subtotal, ?User $customer): int
    {
        $now = now();

        if ($coupon->scope !== 'order' || ! $coupon->is_active) {
            throw new InvalidCouponException('Mã giảm giá không còn hiệu lực.');
        }

        if ($coupon->starts_at->isAfter($now)) {
            throw new InvalidCouponException('Mã giảm giá chưa đến thời gian áp dụng.');
        }

        if ($coupon->ends_at->isBefore($now)) {
            throw new InvalidCouponException('Mã giảm giá đã hết hạn.');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new InvalidCouponException('Mã giảm giá đã hết lượt sử dụng.');
        }

        if ($coupon->usage_limit_per_customer !== null && $customer
            && $this->customerUsageCount($coupon, $customer) >= $coupon->usage_limit_per_customer) {
            throw new InvalidCouponException('Bạn đã dùng hết số lần cho phép của mã giảm giá này.');
        }

        $minOrderAmount = $this->toMoney($coupon->min_order_amount ?? 0);

        if ($subtotal < $minOrderAmount) {
            throw new InvalidCouponException(
                'Mã giảm giá chỉ áp dụng cho đơn từ '.number_format($minOrderAmount, 0, ',', '.').' ₫.'
            );
        }

        return $this->discountAmount($coupon, $subtotal);
    }

    /**
     * Discount amount on a base amount: percent or fixed, capped by
     * `max_discount_amount` and never more than the base itself.
     */
    public function discountAmount(Discount $discount, int $baseAmount): int
    {
        $amount = $discount->discount_type === 'percent'
            ? $this->toMoney($baseAmount * (float) $discount->discount_value / 100)
            : $this->toMoney($discount->discount_value);

        if ($discount->max_discount_amount !== null) {
            $amount = min($amount, $this->toMoney($discount->max_discount_amount));
        }

        return max(0, min($amount, $baseAmount));
    }

    /**
     * @return Collection<int, Discount>
     */
    private function activeDiscountsFor(ProductVariant $variant): Collection
    {
        return $variant->relationLoaded('activeDiscounts')
            ? $variant->activeDiscounts
            : $variant->activeDiscounts()->get();
    }

    /**
     * Orders that used this coupon and still count — a cancelled order gives
     * the use back.
     */
    private function customerUsageCount(Discount $coupon, User $customer): int
    {
        return Order::query()
            ->where('user_id', $customer->id)
            ->where('discount_id', $coupon->id)
            ->where('status', '!=', 'cancelled')
            ->count();
    }

    private function toMoney(mixed $amount): int
    {
        return (int) round((float) $amount);
    }
}
