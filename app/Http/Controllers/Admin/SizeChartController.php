<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSizeChartRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\SizeChart;
use App\Support\AdminPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Size charts shared by brand and/or category; a product page shows the
 * best match (SizeChartResolver) or the one picked on the product.
 */
class SizeChartController extends Controller
{
    public function index(Request $request): View
    {
        $charts = SizeChart::query()
            ->with(['brand', 'category'])
            ->withCount('products')
            ->orderBy('name')->orderBy('id')
            ->paginate(AdminPagination::perPage($request))->withQueryString();

        return view('admin.size-charts.index', ['charts' => $charts]);
    }

    public function create(): View
    {
        return view('admin.size-charts.create', $this->formData(new SizeChart([
            'columns' => ['Vòng ngực (cm)', 'Dài áo (cm)'],
            'rows' => array_map(fn (string $size): array => ['size' => $size], ['S', 'M', 'L', 'XL']),
        ])));
    }

    public function store(SaveSizeChartRequest $request): RedirectResponse
    {
        $chart = SizeChart::query()->create($request->chartData());

        return redirect()->route('admin.size-charts.index')
            ->with('status', "Đã tạo bảng size \"{$chart->name}\".");
    }

    public function edit(SizeChart $sizeChart): View
    {
        return view('admin.size-charts.edit', $this->formData($sizeChart));
    }

    public function update(SaveSizeChartRequest $request, SizeChart $sizeChart): RedirectResponse
    {
        $sizeChart->update($request->chartData());

        return redirect()->route('admin.size-charts.index')
            ->with('status', "Đã lưu bảng size \"{$sizeChart->name}\".");
    }

    public function destroy(SizeChart $sizeChart): RedirectResponse
    {
        // Products that picked it fall back to the automatic choice (FK nullOnDelete).
        $sizeChart->delete();

        return redirect()->route('admin.size-charts.index')->with('status', 'Đã xóa bảng size.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(SizeChart $chart): array
    {
        return [
            'chart' => $chart,
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'categories' => self::categoryPaths(),
        ];
    }

    /**
     * id => "Nam › Áo nam › Áo thun", sorted by path — category names repeat
     * across the tree ("Áo thun" under both Nam and Nữ).
     *
     * @return array<int, string>
     */
    public static function categoryPaths(): array
    {
        $categories = Category::query()->get(['id', 'name', 'parent_id'])->keyBy('id');
        $paths = $categories->map(function (Category $category) use ($categories): string {
            $names = [];
            for ($node = $category; $node !== null && count($names) < 5; $node = $categories[$node->parent_id] ?? null) {
                array_unshift($names, $node->name);
            }

            return implode(' › ', $names);
        })->all();
        asort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        return $paths;
    }
}
