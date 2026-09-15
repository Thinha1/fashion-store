<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionController extends Controller
{
    /**
     * List every brand as its own collection, with a small product preview
     * per brand so the page reads as a set of curated lines rather than a
     * plain brand directory.
     */
    public function index(): View
    {
        $brands = Brand::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($query) => $query->where('status', 'active')])
            ->having('products_count', '>', 0)
            ->with(['products' => function ($query) {
                $query->where('status', 'active')
                    ->with(['images' => fn ($q) => $q->where('is_primary', true)->limit(1)])
                    ->latest('id')
                    ->limit(4);
            }])
            ->orderBy('name')
            ->get();

        return view('storefront.collections.index', ['brands' => $brands]);
    }

    /**
     * A single brand's collection — every product it makes, across every
     * garment type, with an in-page category filter scoped to that brand.
     */
    public function show(Brand $brand, Request $request): View
    {
        abort_unless($brand->is_active, 404);

        $activeCategory = $request->string('category')->toString();

        $products = $brand->products()
            ->where('status', 'active')
            ->when($activeCategory, fn ($query) => $query->whereHas('category', fn ($q) => $q->where('slug', $activeCategory)))
            ->with([
                'category',
                'images' => fn ($query) => $query->where('is_primary', true)->limit(1),
            ])
            ->withSum(['variants as stock_total' => fn ($query) => $query->where('is_active', true)], 'stock_quantity')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $categories = Category::query()
            ->whereIn('id', $brand->products()->where('status', 'active')->pluck('category_id')->unique())
            ->orderBy('sort_order')
            ->get();

        return view('storefront.collections.show', [
            'brand' => $brand,
            'products' => $products,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
        ]);
    }
}
