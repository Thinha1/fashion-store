<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    private const SORTS = ['moi-nhat', 'gia-tang', 'gia-giam'];

    /**
     * Display a paginated grid of active products, with optional brand +
     * category filter and sort — all plain query-string params so the page
     * stays a normal server-rendered GET request.
     */
    public function index(Request $request): View
    {
        $activeBrand = $request->string('brand')->toString();
        $activeCategory = $request->string('category')->toString();
        $sort = in_array($request->string('sort')->toString(), self::SORTS, true)
            ? $request->string('sort')->toString()
            : 'moi-nhat';

        $categoryIds = null;
        if ($activeCategory) {
            $category = Category::with('children.children')->where('slug', $activeCategory)->first();
            $categoryIds = $category ? $this->categoryAndDescendantIds($category) : [-1];
        }

        $products = Product::query()
            ->where('status', 'active')
            ->when($activeBrand, fn ($query) => $query->whereHas('brand', fn ($q) => $q->where('slug', $activeBrand)))
            ->when($categoryIds, fn ($query) => $query->whereIn('category_id', $categoryIds))
            ->with([
                'category',
                'brand',
                'images' => fn ($query) => $query->where('is_primary', true)->limit(1),
            ])
            ->withSum(['variants as stock_total' => fn ($query) => $query->where('is_active', true)], 'stock_quantity')
            ->when($sort === 'gia-tang', fn ($query) => $query->orderBy('base_price'))
            ->when($sort === 'gia-giam', fn ($query) => $query->orderByDesc('base_price'))
            ->when($sort === 'moi-nhat', fn ($query) => $query->latest('id'))
            ->paginate(12)
            ->withQueryString();

        return view('storefront.products.index', [
            'products' => $products,
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->with([
                'children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
            ])])
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'activeBrand' => $activeBrand,
            'activeCategory' => $activeCategory,
            'sort' => $sort,
        ]);
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

    /**
     * Display a single active product with its variants and gallery.
     */
    public function show(Product $product): View
    {
        abort_unless($product->status === 'active', 404);

        $product->load([
            'category',
            'brand',
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order'),
            'variants' => fn ($query) => $query->where('is_active', true)->orderBy('size')->orderBy('color'),
        ]);

        $related = Product::query()
            ->where('status', 'active')
            ->where('id', '!=', $product->id)
            ->where('brand_id', $product->brand_id)
            ->with(['images' => fn ($query) => $query->where('is_primary', true)->limit(1)])
            ->latest('id')
            ->limit(4)
            ->get();

        return view('storefront.products.show', ['product' => $product, 'related' => $related]);
    }
}
