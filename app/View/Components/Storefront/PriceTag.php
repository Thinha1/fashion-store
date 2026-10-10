<?php

namespace App\View\Components\Storefront;

use App\Models\Product;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\ProductPriceTag;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A product card's price: the cheapest variant after its discount, with the
 * original price struck through and a "-X%" badge when something is on sale.
 * The product's variants must be eager-loaded (Product::withListingPrices).
 */
class PriceTag extends Component
{
    public ProductPriceTag $tag;

    public function __construct(public Product $product, PriceCalculator $prices)
    {
        $this->tag = $prices->priceTag($product);
    }

    public function render(): View
    {
        return view('components.storefront.price-tag');
    }
}
