<?php

namespace App\Services\Pricing;

use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The single place prices are computed (BUSINESS_FLOWS.md §4). Catalog, cart,
 * checkout and PlaceOrder all call this instead of doing their own math, and
 * nothing here trusts a price sent by the client.
 *
 * Order of application: the best active variant discount (scope=variant)
 * lowers each line's unit price first; an order voucher (scope=order) then
 * lowers the subtotal; the fixed shipping fee is added and a shipping voucher
 * (scope=shipping) lowers it. An order holds at most one voucher of each
 * kind: the code the customer typed or an automatic one (is_auto_apply),
 * whichever takes off more. An order voucher can be limited to some brands,
 * products or variants (`targets`): it is then worked out on those lines only.
 * All amounts are whole VND.
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
     * Price for a product card, from its active variants. Expects `variants`
     * (with `activeDiscounts`) eager-loaded — see Product::scopeWithListingPrices.
     * A product with no active variant shows its base price.
     */
    public function priceTag(Product $product): ProductPriceTag
    {
        $lines = $product->variants
            ->filter(fn (ProductVariant $variant): bool => (bool) $variant->is_active)
            ->map(function (ProductVariant $variant) use ($product): LinePrice {
                $variant->setRelation('product', $product);

                return $this->priceVariant($variant);
            });

        if ($lines->isEmpty()) {
            $basePrice = $this->toMoney($product->base_price);

            return new ProductPriceTag($basePrice, $basePrice, 0);
        }

        $cheapest = $lines->sortBy(fn (LinePrice $line): int => $line->unitPrice)->first();

        return new ProductPriceTag(
            price: $cheapest->unitPrice,
            originalPrice: $cheapest->originalUnitPrice,
            maxDiscountPercent: (int) $lines->max(fn (LinePrice $line): int => $line->discountPercent()),
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
     * Totals for already-priced lines. `$coupon` is the voucher whose code the
     * customer typed, of either kind; an invalid one throws. Each kind's slot
     * then takes the typed voucher or the best automatic one, whichever takes
     * off more (the typed one wins a tie).
     *
     * @param  Collection<int, LinePrice>  $lines
     *
     * @throws InvalidCouponException
     */
    public function quote(Collection $lines, ?Discount $coupon = null, ?User $customer = null, bool $lockForUpdate = false): OrderQuote
    {
        $subtotal = (int) $lines->sum(fn (LinePrice $line): int => $line->lineTotal());
        $shippingFee = $lines->isEmpty() ? 0 : $this->toMoney(config('store.shipping_fee'));
        $automatic = $lines->isEmpty() ? collect() : $this->automaticVouchers($lockForUpdate);

        // Order voucher: lowers the subtotal.
        [$orderVoucher, $discountAmount] = $this->bestVoucher(
            $coupon?->scope === 'order' ? $coupon : null,
            $automatic->where('scope', 'order'),
            fn (Discount $voucher): int => $this->voucherDiscount($voucher, $this->eligibleAmount($voucher, $lines, $subtotal), $shippingFee, $customer),
        );

        // Shipping voucher: its minimum order is checked against the subtotal
        // after the order voucher, and it never takes off more than the fee.
        $afterOrderVoucher = $subtotal - $discountAmount;
        [$shippingVoucher, $shippingDiscountAmount] = $this->bestVoucher(
            $coupon?->scope === 'shipping' ? $coupon : null,
            $automatic->where('scope', 'shipping'),
            fn (Discount $voucher): int => $this->voucherDiscount($voucher, $afterOrderVoucher, $shippingFee, $customer),
        );

        $notice = null;
        if ($coupon && ! $coupon->is($orderVoucher) && ! $coupon->is($shippingVoucher)) {
            $better = $coupon->scope === 'shipping' ? $shippingVoucher : $orderVoucher;
            $notice = "Mã {$coupon->code} giảm ít hơn ưu đãi tự động \"{$better?->voucherLabel()}\", nên đơn dùng ưu đãi tự động.";
        }

        return new OrderQuote(
            lines: $lines,
            subtotal: $subtotal,
            discountAmount: $discountAmount,
            shippingFee: $shippingFee,
            grandTotal: $subtotal - $discountAmount + $shippingFee - $shippingDiscountAmount,
            coupon: $orderVoucher,
            shippingDiscountAmount: $shippingDiscountAmount,
            shippingVoucher: $shippingVoucher,
            notice: $notice,
            freeShippingGap: $this->freeShippingGap($automatic, $afterOrderVoucher, $shippingFee, $shippingDiscountAmount, $customer),
        );
    }

    /**
     * Look up a voucher (order or shipping) by the code the customer typed,
     * case-insensitively.
     *
     * @throws InvalidCouponException
     */
    public function findCoupon(string $code, bool $lockForUpdate = false): Discount
    {
        $coupon = Discount::query()
            ->whereIn('scope', Discount::VOUCHER_SCOPES)
            ->where('code', mb_strtoupper(trim($code)))
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first();

        if (! $coupon) {
            throw new InvalidCouponException('Mã giảm giá không tồn tại.');
        }

        return $coupon;
    }

    /**
     * How much an order voucher takes off this subtotal, after checking every rule.
     *
     * @throws InvalidCouponException
     */
    public function couponDiscount(Discount $coupon, int $subtotal, ?User $customer): int
    {
        if ($coupon->scope !== 'order') {
            throw new InvalidCouponException('Mã giảm giá không còn hiệu lực.');
        }

        return $this->voucherDiscount($coupon, $subtotal, 0, $customer);
    }

    /**
     * How much a voucher takes off: an order voucher off `$orderAmount`, a
     * shipping voucher off `$shippingFee`. Both need `$orderAmount` to reach
     * the voucher's minimum order.
     *
     * @throws InvalidCouponException
     */
    public function voucherDiscount(Discount $coupon, int $orderAmount, int $shippingFee, ?User $customer): int
    {
        $now = now();

        if (! $coupon->isVoucher() || ! $coupon->is_active) {
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

        if ($orderAmount < $minOrderAmount) {
            $minimum = number_format($minOrderAmount, 0, ',', '.');

            throw new InvalidCouponException($coupon->scope === 'order' && $coupon->hasTargets()
                ? "Mã giảm giá cần các sản phẩm áp dụng có tổng từ {$minimum} ₫."
                : "Mã giảm giá chỉ áp dụng cho đơn từ {$minimum} ₫.");
        }

        return $this->discountAmount($coupon, $coupon->scope === 'shipping' ? $shippingFee : $orderAmount);
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
     * What an order voucher is worked out on: the whole subtotal, or only the
     * lines it is limited to (brands, products, variants).
     *
     * @param  Collection<int, LinePrice>  $lines
     *
     * @throws InvalidCouponException when it is limited and nothing in the cart qualifies
     */
    private function eligibleAmount(Discount $voucher, Collection $lines, int $subtotal): int
    {
        if ($voucher->scope !== 'order' || ! $voucher->hasTargets()) {
            return $subtotal;
        }

        $eligible = (int) $lines
            ->filter(fn (LinePrice $line): bool => $voucher->appliesToVariant($line->variant))
            ->sum(fn (LinePrice $line): int => $line->lineTotal());

        if ($eligible === 0) {
            throw new InvalidCouponException("Mã giảm giá chỉ áp dụng cho {$voucher->targetSummary()}; giỏ hàng chưa có sản phẩm phù hợp.");
        }

        return $eligible;
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
     * Orders that used this voucher and still count — a cancelled order gives
     * the use back.
     */
    private function customerUsageCount(Discount $coupon, User $customer): int
    {
        return Order::query()
            ->where('user_id', $customer->id)
            ->where($coupon->scope === 'shipping' ? 'shipping_discount_id' : 'discount_id', $coupon->id)
            ->where('status', '!=', 'cancelled')
            ->count();
    }

    /**
     * Automatic vouchers of both kinds that are on and inside their dates.
     * Usage limits and the minimum order are checked per quote.
     *
     * @return Collection<int, Discount>
     */
    private function automaticVouchers(bool $lockForUpdate): Collection
    {
        return Discount::query()
            ->whereIn('scope', Discount::VOUCHER_SCOPES)
            ->where('is_auto_apply', true)
            ->currentlyActive()
            ->orderBy('id')
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->get();
    }

    /**
     * The voucher that takes off the most: the typed one (which must be
     * valid) or an automatic one that passes its rules; the typed one wins a
     * tie and an automatic one that fails a rule is skipped.
     *
     * @param  Collection<int, Discount>  $automatic
     * @param  callable(Discount): int  $amountOf
     * @return array{0: ?Discount, 1: int}
     *
     * @throws InvalidCouponException
     */
    private function bestVoucher(?Discount $typed, Collection $automatic, callable $amountOf): array
    {
        $best = $typed;
        $bestAmount = $typed ? $amountOf($typed) : 0;

        foreach ($automatic as $voucher) {
            if ($typed?->is($voucher)) {
                continue;
            }

            try {
                $amount = $amountOf($voucher);
            } catch (InvalidCouponException) {
                continue;
            }

            if ($amount > $bestAmount) {
                [$best, $bestAmount] = [$voucher, $amount];
            }
        }

        return $bestAmount > 0 || $typed ? [$best, $bestAmount] : [null, 0];
    }

    /**
     * How much more the customer must buy for an automatic shipping voucher
     * to cover the whole fee, when one would and none does yet.
     *
     * @param  Collection<int, Discount>  $automatic
     */
    private function freeShippingGap(Collection $automatic, int $orderAmount, int $shippingFee, int $shippingDiscount, ?User $customer): ?int
    {
        if ($shippingFee === 0 || $shippingDiscount >= $shippingFee) {
            return null;
        }

        $thresholds = $automatic
            ->where('scope', 'shipping')
            ->filter(function (Discount $voucher) use ($orderAmount, $shippingFee, $customer): bool {
                $minimum = $this->toMoney($voucher->min_order_amount ?? 0);
                if ($minimum <= $orderAmount || $this->discountAmount($voucher, $shippingFee) < $shippingFee) {
                    return false;
                }

                try {
                    // Every rule but the minimum order must already pass.
                    $this->voucherDiscount($voucher, $minimum, $shippingFee, $customer);
                } catch (InvalidCouponException) {
                    return false;
                }

                return true;
            })
            ->map(fn (Discount $voucher): int => $this->toMoney($voucher->min_order_amount));

        return $thresholds->isEmpty() ? null : $thresholds->min() - $orderAmount;
    }

    private function toMoney(mixed $amount): int
    {
        return (int) round((float) $amount);
    }
}
