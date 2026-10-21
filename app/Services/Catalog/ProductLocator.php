<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Meilisearch\Endpoints\Indexes;
use Throwable;

/**
 * Finds the one product a staff member means by however they named it in
 * the admin assistant chat ("áo polo Coolmate", a SKU, a slug), so the
 * assistant can open that product's edit page. Archived products count too:
 * staff edit those as well.
 */
class ProductLocator
{
    private const MAX_WORDS = 8;

    public function find(string $reference): ?Product
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        return $this->bySku($reference)
            ?? Product::query()->where('name', $reference)->orWhere('slug', $reference)->first()
            ?? $this->bySearchIndex($reference)
            ?? $this->byWords($reference);
    }

    private function bySku(string $reference): ?Product
    {
        $variant = ProductVariant::query()
            ->whereIn('sku', array_unique([$reference, mb_strtoupper($reference)]))
            ->with('product')
            ->first();

        return $variant?->product;
    }

    /**
     * Meilisearch tolerates typos and missing diacritics; every word must
     * match so a vague phrase never opens an unrelated product. Only active
     * products are indexed, so archived ones fall through to byWords().
     */
    private function bySearchIndex(string $reference): ?Product
    {
        if (config('scout.driver') !== 'meilisearch') {
            return null;
        }

        try {
            $id = Product::search($reference, fn (Indexes $index, string $query, array $options) => $index->search(
                $query,
                [...$options, 'matchingStrategy' => 'all'],
            ))->take(1)->keys()->first();
        } catch (Throwable $exception) {
            Log::warning('Product locator fell back to the database: '.$exception->getMessage());

            return null;
        }

        return $id === null ? null : Product::query()->find($id);
    }

    /**
     * Every word must appear in the product or brand name; the newest
     * product wins a tie.
     */
    private function byWords(string $reference): ?Product
    {
        $words = array_slice(preg_split('/\s+/u', $reference, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, self::MAX_WORDS);

        $query = Product::query();
        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $like)
                ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', $like)));
        }

        return $query->latest('id')->first();
    }
}
