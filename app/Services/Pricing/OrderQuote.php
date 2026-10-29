<?php

namespace App\Services\Pricing;

use App\Models\Discount;
use Illuminate\Support\Collection;

/**
 * Totals for a set of lines: subtotal → order voucher → shipping fee →
 * shipping voucher → grand total. These are exactly the numbers snapshotted
 * onto `orders`.
 */
final readonly class OrderQuote
{
    /**
     * @param  Collection<int, LinePrice>  $lines
     * @param  ?Discount  $coupon  the order voucher applied (typed or automatic)
     * @param  ?Discount  $shippingVoucher  the shipping voucher applied (typed or automatic)
     * @param  ?string  $notice  why the code the customer typed is not the one applied
     * @param  ?int  $freeShippingGap  how much more to buy for an automatic voucher to cover the whole shipping fee
     */
    public function __construct(
        public Collection $lines,
        public int $subtotal,
        public int $discountAmount,
        public int $shippingFee,
        public int $grandTotal,
        public ?Discount $coupon,
        public int $shippingDiscountAmount = 0,
        public ?Discount $shippingVoucher = null,
        public ?string $notice = null,
        public ?int $freeShippingGap = null,
    ) {}
}
