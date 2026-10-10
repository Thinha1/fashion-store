<?php

namespace App\Services\Pricing;

use App\Models\Discount;
use Illuminate\Support\Collection;

/**
 * Totals for a set of lines: subtotal → coupon discount → fixed shipping fee →
 * grand total. These are exactly the numbers snapshotted onto `orders`.
 */
final readonly class OrderQuote
{
    /**
     * @param  Collection<int, LinePrice>  $lines
     */
    public function __construct(
        public Collection $lines,
        public int $subtotal,
        public int $discountAmount,
        public int $shippingFee,
        public int $grandTotal,
        public ?Discount $coupon,
    ) {}
}
