<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The product shelves on the home page (featured, on sale, newest). Each one
 * shows four products and the carousel fetches the next four on demand from
 * the home.shelf route, so the queries live here for both.
 */
class HomeShelves
{
    public const PER_PAGE = 4;

    public const SHELVES = ['noi-bat', 'giam-gia', 'moi-ve'];

    /**
     * One page of a shelf, newest first, skipping the given product ids (the
     * featured shelf skips what the cover already shows).
     *
     * @param  list<int>  $except
     */
    public function page(string $shelf, array $except = [], int $page = 1): Paginator
    {
        return $this->query($shelf)
            ->when($except !== [], fn (Builder $query) => $query->whereNotIn('id', $except))
            ->simplePaginate(self::PER_PAGE, ['*'], 'page', max(1, $page));
    }

    /**
     * Active products with their brand, primary image and listing prices.
     */
    public function listing(): Builder
    {
        return Product::query()
            ->where('status', 'active')
            ->with(['brand', 'images' => fn ($query) => $query->where('is_primary', true)->limit(1)])
            ->withListingPrices()
            ->latest('id');
    }

    private function query(string $shelf): Builder
    {
        return match ($shelf) {
            'noi-bat' => $this->listing()->where('is_featured', true),
            'giam-gia' => $this->listing()->whereHas('variants', fn (Builder $variants) => $variants
                ->where('is_active', true)
                ->whereHas('activeDiscounts')),
            default => $this->listing(),
        };
    }
}
