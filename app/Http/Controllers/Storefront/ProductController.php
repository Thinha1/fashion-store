<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogSearch;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * The catalog: text search plus category, brand, size, color, price and
     * in-stock filters and a sort, all plain query-string params so every
     * combination is a shareable, server-rendered URL.
     */
    public function index(Request $request, CatalogSearch $catalog): View
    {
        $filters = CatalogFilters::fromRequest($request);

        return view('storefront.products.index', [
            'products' => $catalog->paginate($filters, 12)->withQueryString(),
            'filters' => $filters,
            'filterOptions' => $catalog->filterOptions(),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->with([
                'children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
            ])])
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    /**
     * Display a single active product with its variants and gallery.
     */
    public function show(Product $product, PriceCalculator $prices): View
    {
        abort_unless($product->status === 'active', 404);

        $product->load([
            'category',
            'brand',
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order'),
            'images.variant',
            'variants' => fn ($query) => $query->where('is_active', true)->orderByRaw(ProductVariant::sizeOrderRaw())->orderBy('id'),
            'variants.activeDiscounts',
        ]);

        $variantPrices = $product->variants->mapWithKeys(function (ProductVariant $variant) use ($product, $prices): array {
            $variant->setRelation('product', $product);

            return [$variant->id => $prices->priceVariant($variant)];
        });

        $related = Product::query()
            ->where('status', 'active')
            ->where('id', '!=', $product->id)
            ->where('brand_id', $product->brand_id)
            ->with(['brand', 'images' => fn ($query) => $query->where('is_primary', true)->limit(1)])
            ->withListingPrices()
            ->latest('id')
            ->limit(4)
            ->get();

        return view('storefront.products.show', [
            'product' => $product,
            'variantPrices' => $variantPrices,
            'related' => $related,
        ]);
    }
}
