<?php

namespace App\Services\Ai;

use App\Models\Product;

/**
 * Builds a short "product the customer is currently viewing" line for the
 * shopping-assist prompt, from a real, active product id only — used by
 * both the JSON and streaming storefront assist controllers (see the
 * `data-current-product-id` attribute in layouts/app.blade.php) so the
 * model can reference the page a customer is on without ever being trusted
 * to supply the product itself.
 */
class CurrentProductContextDescriber
{
    public function describe(?int $productId): string
    {
        if (! $productId) {
            return '';
        }

        $product = Product::query()->where('status', 'active')->with('category')->find($productId);

        if (! $product) {
            return '';
        }

        return sprintf(
            '%s (danh mục: %s, giá: %s đ)',
            $product->name,
            $product->category?->name ?? 'chưa phân loại',
            number_format((float) $product->base_price, 0, ',', '.'),
        );
    }
}
