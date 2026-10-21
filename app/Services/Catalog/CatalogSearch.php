<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Log;
use Meilisearch\Endpoints\Indexes;
use Throwable;

/**
 * The storefront catalog query: text search, filters, sort and pagination.
 *
 * Meilisearch only answers "which products match these words, best first"
 * (typos, missing diacritics, synonyms). Everything else is applied in
 * MySQL, because stock and the discounted price change all the time and the
 * price must come from PriceCalculator, the one place that knows the
 * discount rules. If Meilisearch is not configured or is down, the words are
 * matched with LIKE instead so the page keeps working.
 */
class CatalogSearch
{
    /**
     * Upper bound on search hits taken into the filter step; well past what
     * a shopper pages through.
     */
    private const MAX_SEARCH_HITS = 500;

    private const MAX_SEARCH_WORDS = 6;

    public function __construct(private readonly PriceCalculator $prices) {}

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(CatalogFilters $filters, int $perPage): LengthAwarePaginator
    {
        $matchedIds = $filters->q === '' ? null : $this->matchingProductIds($filters->q);

        $query = $this->filteredQuery($filters, $matchedIds);
        $this->withCardData($query);

        if ($filters->sort === 'moi-nhat' && ! $filters->hasPriceFilter()) {
            return $query->latest('id')->paginate($perPage);
        }

        return $this->paginateInMemory($query->get(), $filters, $matchedIds ?? [], $perPage);
    }

    /**
     * Sizes and colors that exist on an active variant of an active
     * product, for the filter chips. Sizes keep the standard S → 4XL order,
     * anything else (e.g. "Free Size") after.
     *
     * @return array{sizes: list<string>, colors: list<string>}
     */
    public function filterOptions(): array
    {
        $variants = ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', fn (Builder $query) => $query->where('status', 'active'));

        $sizes = (clone $variants)->distinct()->pluck('size')->all();
        usort($sizes, function (string $a, string $b): int {
            $rank = fn (string $size): int => ($index = array_search($size, ProductVariant::SIZES, true)) === false
                ? count(ProductVariant::SIZES)
                : $index;

            return [$rank($a), $a] <=> [$rank($b), $b];
        });

        return [
            'sizes' => $sizes,
            'colors' => (clone $variants)->distinct()->orderBy('color')->pluck('color')->all(),
        ];
    }

    /**
     * Ids of products matching the words, most relevant first.
     *
     * @return list<int>
     */
    private function matchingProductIds(string $q): array
    {
        if (config('scout.driver') === 'meilisearch') {
            try {
                // "all": every word must match (typos allowed). The default
                // drops trailing words until something matches, so
                // "áo sơ mi trắng" would fall back to every "áo".
                $search = Product::search($q, fn (Indexes $index, string $query, array $options) => $index->search(
                    $query,
                    [...$options, 'matchingStrategy' => 'all'],
                ));

                return $search->take(self::MAX_SEARCH_HITS)->keys()
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all();
            } catch (Throwable $exception) {
                Log::warning('Catalog search fell back to the database: '.$exception->getMessage());
            }
        }

        return $this->databaseMatches($q);
    }

    /**
     * LIKE fallback: every word must appear somewhere (name, description,
     * brand, category, variant color or SKU). No typo tolerance, but the
     * database collation already ignores case and most diacritics.
     *
     * @return list<int>
     */
    private function databaseMatches(string $q): array
    {
        $words = array_slice(preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, self::MAX_SEARCH_WORDS);

        $query = Product::query()->where('status', 'active');
        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', $like))
                ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like))
                ->orWhereHas('variants', fn (Builder $variant) => $variant->where('is_active', true)
                    ->where(fn (Builder $variant) => $variant->where('color', 'like', $like)->orWhere('sku', 'like', $like))));
        }

        return $query->orderByDesc('is_featured')->latest('id')->limit(self::MAX_SEARCH_HITS)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>|null  $matchedIds  null when there is no text search
     * @return Builder<Product>
     */
    private function filteredQuery(CatalogFilters $filters, ?array $matchedIds): Builder
    {
        $categoryIds = null;
        if ($filters->category !== '') {
            $category = Category::with('children.children')->where('slug', $filters->category)->first();
            $categoryIds = $category ? $this->categoryAndDescendantIds($category) : [-1];
        }

        $filtersVariants = $filters->sizes !== [] || $filters->colors !== [] || $filters->inStock;

        return Product::query()
            ->where('status', 'active')
            ->when($matchedIds !== null, fn (Builder $query) => $query->whereIn('id', $matchedIds ?: [-1]))
            ->when($filters->brand !== '', fn (Builder $query) => $query->whereHas('brand', fn (Builder $brand) => $brand->where('slug', $filters->brand)))
            ->when($categoryIds, fn (Builder $query) => $query->whereIn('category_id', $categoryIds))
            // One variant has to satisfy every variant filter at once, so
            // "M + Đen + còn hàng" means a black M that is in stock.
            ->when($filtersVariants, fn (Builder $query) => $query->whereHas('variants', fn (Builder $variant) => $variant
                ->where('is_active', true)
                ->when($filters->sizes !== [], fn (Builder $variant) => $variant->whereIn('size', $filters->sizes))
                ->when($filters->colors !== [], fn (Builder $variant) => $variant->whereIn('color', $filters->colors))
                ->when($filters->inStock, fn (Builder $variant) => $variant->where('stock_quantity', '>', 0))));
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function withCardData(Builder $query): void
    {
        $query->with([
            'category',
            'brand',
            'images' => fn ($images) => $images->where('is_primary', true)->limit(1),
        ])
            ->withSum(['variants as stock_total' => fn ($variants) => $variants->where('is_active', true)], 'stock_quantity')
            ->withListingPrices();
    }

    /**
     * Price filters and price/relevance sorts need each product's displayed
     * price (after its running discount), which only PriceCalculator knows,
     * so the candidates are priced, filtered and sorted here, then paged.
     *
     * @param  Collection<int, Product>  $products
     * @param  list<int>  $matchedIds
     * @return LengthAwarePaginator<int, Product>
     */
    private function paginateInMemory(Collection $products, CatalogFilters $filters, array $matchedIds, int $perPage): LengthAwarePaginator
    {
        $priced = $products->map(fn (Product $product): array => [
            'product' => $product,
            'price' => $this->prices->priceTag($product)->price,
        ]);

        if ($filters->hasPriceFilter()) {
            $priced = $priced->filter(fn (array $row): bool => ($filters->priceMin === null || $row['price'] >= $filters->priceMin)
                && ($filters->priceMax === null || $row['price'] <= $filters->priceMax));
        }

        $rank = array_flip($matchedIds);
        $newestFirst = fn (array $a, array $b): int => $b['product']->id <=> $a['product']->id;
        $sorted = match ($filters->sort) {
            'gia-tang' => $priced->sortBy([['price', 'asc'], $newestFirst]),
            'gia-giam' => $priced->sortBy([['price', 'desc'], $newestFirst]),
            'lien-quan' => $priced->sortBy(fn (array $row): int => $rank[$row['product']->id] ?? PHP_INT_MAX),
            default => $priced->sortByDesc(fn (array $row): int => $row['product']->id),
        };

        $page = Paginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $sorted->pluck('product')->slice(($page - 1) * $perPage, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * Flatten a category and every descendant (2 levels of nested
     * "children" must already be eager-loaded) into a list of ids, so a
     * filter on any level of the tree — group, garment type, or specific
     * style — matches every product beneath it.
     *
     * @return list<int>
     */
    private function categoryAndDescendantIds(Category $category): array
    {
        $ids = [$category->id];
        foreach ($category->children as $child) {
            $ids[] = $child->id;
            foreach ($child->children as $grandchild) {
                $ids[] = $grandchild->id;
            }
        }

        return $ids;
    }
}
