<?php

namespace App\Services\Cart;

use App\Models\CartItem;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\Pricing\LinePrice;

/**
 * One cart row as the customer sees it: the stored item, its current
 * server-side price, and why it can't be bought right now (if it can't).
 */
final readonly class CartLine
{
    public function __construct(
        public CartItem $item,
        public LinePrice $price,
        public ?string $problem,
    ) {}

    public function variant(): ProductVariant
    {
        return $this->item->productVariant;
    }

    public function isAvailable(): bool
    {
        return $this->problem === null;
    }

    /**
     * The variant's own photo if it has one, otherwise the product's primary photo.
     */
    public function image(): ?ProductImage
    {
        $images = $this->variant()->product?->images ?? collect();

        return $images->firstWhere('product_variant_id', $this->variant()->id)
            ?? $images->firstWhere('is_primary', true)
            ?? $images->first();
    }
}
