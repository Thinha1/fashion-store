<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\SizeChart;
use Illuminate\Support\Collection;

/**
 * Which size chart a product page shows:
 *
 * 1. the chart picked on the product itself (products.size_chart_id);
 * 2. otherwise the best automatic match among charts whose brand is the
 *    product's brand or "any", and whose category is the product's
 *    category, one of its parents, or "any". A brand match beats a
 *    category match, and a nearer category beats a farther one — so
 *    "Coolmate · Áo nam" wins over "Coolmate · Nam", which wins over
 *    "Mọi thương hiệu · Áo nam".
 */
class SizeChartResolver
{
    public function forProduct(Product $product): ?SizeChart
    {
        if ($product->size_chart_id !== null) {
            $chosen = SizeChart::query()->with(['brand', 'category'])->find($product->size_chart_id);
            if ($chosen !== null) {
                return $chosen;
            }
        }

        return $this->automatic($product->brand_id, $product->category_id);
    }

    /**
     * The chart a product with this brand and category gets when none is picked by hand.
     */
    public function automatic(?int $brandId, ?int $categoryId): ?SizeChart
    {
        // Product category first, then its parents, nearest first.
        $lineage = $this->lineage($categoryId);

        $candidates = SizeChart::query()
            ->with(['brand', 'category'])
            ->where(fn ($query) => $query->whereNull('brand_id')->when($brandId, fn ($query) => $query->orWhere('brand_id', $brandId)))
            ->where(fn ($query) => $query->whereNull('category_id')->when($lineage !== [], fn ($query) => $query->orWhereIn('category_id', $lineage)))
            ->orderBy('id')
            ->get();

        return $candidates->sortByDesc(fn (SizeChart $chart): int => $this->score($chart, $lineage))->first();
    }

    /**
     * Higher is a better match; ties keep the oldest chart (query order).
     *
     * @param  list<int>  $lineage
     */
    private function score(SizeChart $chart, array $lineage): int
    {
        $categoryScore = $chart->category_id === null ? 0 : count($lineage) - (int) array_search($chart->category_id, $lineage, true);

        return ($chart->brand_id !== null ? 100 : 0) + $categoryScore;
    }

    /**
     * @return list<int> the category id and its ancestors' ids, nearest first
     */
    private function lineage(?int $categoryId): array
    {
        $ids = [];
        /** @var Collection<int, Category> $categories */
        $categories = Category::query()->get(['id', 'parent_id'])->keyBy('id');
        while ($categoryId !== null && isset($categories[$categoryId]) && ! in_array($categoryId, $ids, true)) {
            $ids[] = $categoryId;
            $categoryId = $categories[$categoryId]->parent_id;
        }

        return $ids;
    }
}
