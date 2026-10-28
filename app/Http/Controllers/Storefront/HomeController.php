<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\HomeShelves;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** Brands on the home page's ink band; the next cell links to the rest. */
    public const BRANDS_ON_HOME = 8;

    /**
     * The storefront home: a cover of featured products led by a menswear
     * piece, shelves of featured, discounted and newest products (four at a
     * time, more on demand), the category groups and the brands.
     */
    public function __invoke(HomeShelves $shelves): View
    {
        $cover = $this->cover($shelves);
        $except = $cover->pluck('id')->all();

        $shelfPages = collect(HomeShelves::SHELVES)->mapWithKeys(fn (string $shelf) => [
            $shelf => $shelves->page($shelf, $shelf === 'noi-bat' ? $except : []),
        ]);

        return view('storefront.home', [
            'cover' => $cover,
            'coverExcept' => $except,
            'shelves' => $shelfPages,
            'featured' => $cover->where('is_featured', true)->concat($shelfPages['noi-bat']->items()),
            'categoryGroups' => $this->categoryGroups(),
            'brands' => Brand::query()
                ->where('is_active', true)
                ->withCount(['products' => fn (Builder $products) => $products->where('status', 'active')])
                ->orderByDesc('products_count')
                ->orderBy('name')
                ->get()
                ->filter(fn (Brand $brand) => $brand->products_count > 0)
                ->values(),
            'brandsShown' => self::BRANDS_ON_HOME,
        ]);
    }

    /**
     * Six featured products for the cover, a menswear piece first. Falls
     * back to the newest products when nothing is featured.
     *
     * @return Collection<int, Product>
     */
    private function cover(HomeShelves $shelves): Collection
    {
        $featured = $shelves->listing()->where('is_featured', true)->limit(12)->get();
        $pool = $featured->isNotEmpty() ? $featured : $shelves->listing()->limit(6)->get();

        $menswear = Category::withDescendantIds(Category::query()->where('slug', 'nam')->pluck('id')->all());
        $lead = $pool->first(fn (Product $product) => in_array((int) $product->category_id, $menswear, true)) ?? $pool->first();

        return $lead
            ? $pool->reject(fn (Product $product) => $product->is($lead))->prepend($lead)->take(6)->values()
            : collect();
    }

    /**
     * The second-level groups (Áo nam, Quần nữ…) with their leaf categories
     * and how many active products sit under each group.
     *
     * @return Collection<int, Category>
     */
    private function categoryGroups(): Collection
    {
        $counts = Product::query()
            ->where('status', 'active')
            ->selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        return Category::query()
            ->where('is_active', true)
            ->whereHas('parent', fn (Builder $parent) => $parent->whereNull('parent_id'))
            ->with(['children' => fn ($children) => $children->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get()
            ->each(function (Category $group) use ($counts): void {
                $group->children->each(fn (Category $leaf) => $leaf->setAttribute('products_total', (int) ($counts[$leaf->id] ?? 0)));
                $group->setAttribute('products_total', (int) ($counts[$group->id] ?? 0) + $group->children->sum('products_total'));
            })
            ->filter(fn (Category $group) => $group->products_total > 0)
            ->values();
    }
}
