<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\SizeChart;
use Illuminate\Database\Seeder;

/**
 * Demo size charts (database/seeders/data/size-charts.json), one per brand
 * and Nam/Nữ (or Áo/Quần) group plus a shop-wide fallback. Matched by name,
 * so running it again updates instead of duplicating. Brands and
 * categories are referenced by slug; a chart whose brand or category is
 * missing (e.g. CatalogDemoSeeder not run) is skipped.
 */
class SizeChartSeeder extends Seeder
{
    public function run(): void
    {
        $charts = json_decode((string) file_get_contents(database_path('seeders/data/size-charts.json')), true);
        $brands = Brand::query()->pluck('id', 'slug');
        $categories = Category::query()->pluck('id', 'slug');

        foreach ($charts as $chart) {
            $brandId = $chart['brand'] === null ? null : ($brands[$chart['brand']] ?? false);
            $categoryId = $chart['category'] === null ? null : ($categories[$chart['category']] ?? false);
            if ($brandId === false || $categoryId === false) {
                continue;
            }

            SizeChart::query()->updateOrCreate(['name' => $chart['name']], [
                'brand_id' => $brandId,
                'category_id' => $categoryId,
                'columns' => $chart['columns'],
                'rows' => $chart['rows'],
                'note' => $chart['note'],
            ]);
        }
    }
}
