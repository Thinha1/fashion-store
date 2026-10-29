<?php

namespace App\Services\Pricing;

use App\Models\Discount;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The brands, products and variants picked on the discount form. A variant
 * discount is created for every variant they cover; an order voucher keeps
 * them in its `targets` column.
 */
class DiscountTargets
{
    /** Most variants one form submission may create discounts for. */
    public const MAX_VARIANTS = 500;

    /**
     * Whatever the form sent, as lists of unique positive ids by kind.
     *
     * @return array{brand: list<int>, product: list<int>, variant: list<int>}
     */
    public function normalize(mixed $input): array
    {
        $input = is_array($input) ? $input : [];

        return array_combine(Discount::TARGET_TYPES, array_map(
            fn (string $type): array => array_values(array_unique(array_filter(
                array_map('intval', array_filter((array) ($input[$type] ?? []), 'is_scalar')),
                fn (int $id): bool => $id > 0,
            ))),
            Discount::TARGET_TYPES,
        ));
    }

    public function isEmpty(array $targets): bool
    {
        return array_filter($targets) === [];
    }

    /**
     * Variants on sale (active, of an active product) that are picked, belong
     * to a picked product or to a picked brand.
     *
     * @param  array{brand: list<int>, product: list<int>, variant: list<int>}  $targets
     * @return Collection<int, ProductVariant>
     */
    public function variants(array $targets): Collection
    {
        if ($this->isEmpty($targets)) {
            return collect();
        }

        return ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', fn (Builder $product) => $product->where('status', 'active'))
            ->where(function (Builder $query) use ($targets): void {
                $query->when($targets['variant'], fn (Builder $q, array $ids) => $q->orWhereIn('id', $ids))
                    ->when($targets['product'], fn (Builder $q, array $ids) => $q->orWhereIn('product_id', $ids))
                    ->when($targets['brand'], fn (Builder $q, array $ids) => $q->orWhereHas('product', fn (Builder $product) => $product->whereIn('brand_id', $ids)));
            })
            ->orderBy('product_id')
            ->orderBy('id')
            ->get();
    }
}
