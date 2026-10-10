<?php

namespace App\Services\Pricing;

/**
 * What a product card shows: the cheapest variant's price after its
 * discount, that variant's original price, and the deepest discount on any
 * variant (for a "-20%" badge). Whole VND.
 */
final readonly class ProductPriceTag
{
    public function __construct(
        public int $price,
        public int $originalPrice,
        public int $maxDiscountPercent,
    ) {}

    public function isDiscounted(): bool
    {
        return $this->price < $this->originalPrice;
    }
}
