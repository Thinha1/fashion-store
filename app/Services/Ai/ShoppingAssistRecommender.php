<?php

namespace App\Services\Ai;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Turns a parsed search filter (see ShoppingAssistResponseParser) into real
 * product rows via a plain Eloquent query — the AI never sees or picks a
 * product itself, only this query does, so a hallucinated name/price can
 * never reach the customer. Mirrors the active-product filtering pattern
 * already used by Storefront\ProductController::index.
 */
class ShoppingAssistRecommender
{
    private const MAX_RESULTS = 8;

    /**
     * @param  array{category: string, price_min: int|null, price_max: int|null, sizes: array<int, string>, colors: array<int, string>, keywords: array<int, string>}  $filter
     * @return Collection<int, Product>
     */
    public function search(array $filter): Collection
    {
        $sizes = $filter['sizes'];
        $colors = $filter['colors'];

        return Product::query()
            ->where('status', 'active')
            ->when($filter['category'], fn ($query) => $query->whereHas(
                'category', fn ($query) => $query->where('name', $filter['category'])
            ))
            ->when($filter['price_min'], fn ($query) => $query->where('base_price', '>=', $filter['price_min']))
            ->when($filter['price_max'], fn ($query) => $query->where('base_price', '<=', $filter['price_max']))
            ->when($sizes || $colors, fn ($query) => $query->whereHas('variants', function ($query) use ($sizes, $colors) {
                $query->where('is_active', true)
                    ->where('stock_quantity', '>', 0)
                    ->when($sizes, fn ($query) => $query->whereIn('size', $sizes))
                    ->when($colors, fn ($query) => $query->whereIn('color', $colors));
            }))
            ->when($filter['keywords'], fn ($query) => $query->where(function ($query) use ($filter) {
                foreach ($filter['keywords'] as $keyword) {
                    // Escapes the SQL LIKE wildcards `%`/`_` themselves (not a SQL
                    // injection concern — the value is still parameter-bound —
                    // just so a keyword containing a literal "%" or "_" doesn't
                    // silently match everything/one extra character).
                    $escaped = addcslashes($keyword, '%_\\');
                    $query->orWhere('name', 'like', "%{$escaped}%")->orWhere('description', 'like', "%{$escaped}%");
                }
            }))
            ->with(['brand', 'category', 'images' => fn ($query) => $query->where('is_primary', true)->limit(1)])
            ->withSum(['variants as stock_total' => fn ($query) => $query->where('is_active', true)], 'stock_quantity')
            ->orderByDesc('is_featured')
            ->latest('id')
            ->limit(self::MAX_RESULTS)
            ->get();
    }
}
