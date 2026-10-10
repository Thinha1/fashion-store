<?php

namespace App\Services\Pricing;

use App\Models\Discount;
use App\Models\ProductVariant;

/**
 * The server-side price of one variant line (a cart or order line), after the
 * best currently active variant discount. Amounts are whole VND.
 */
final readonly class LinePrice
{
    public function __construct(
        public ProductVariant $variant,
        public int $quantity,
        public int $originalUnitPrice,
        public int $unitDiscountAmount,
        public int $unitPrice,
        public ?Discount $discount,
    ) {}

    public function lineTotal(): int
    {
        return $this->unitPrice * $this->quantity;
    }

    /**
     * Total variant discount for the whole line — the value snapshotted into
     * `order_items.discount_amount`.
     */
    public function lineDiscountAmount(): int
    {
        return $this->unitDiscountAmount * $this->quantity;
    }

    public function isDiscounted(): bool
    {
        return $this->unitDiscountAmount > 0;
    }

    /**
     * Rounded discount percentage for badges such as "-20%".
     */
    public function discountPercent(): int
    {
        if ($this->originalUnitPrice === 0) {
            return 0;
        }

        return (int) round($this->unitDiscountAmount * 100 / $this->originalUnitPrice);
    }
}
