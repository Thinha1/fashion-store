<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Display the storefront home page, including a featured-products rail
     * that links into the real catalog at /san-pham.
     */
    public function __invoke(): View
    {
        $featured = Product::query()
            ->where('status', 'active')
            ->where('is_featured', true)
            ->with(['brand', 'images' => fn ($query) => $query->where('is_primary', true)->limit(1)])
            ->latest('id')
            ->limit(8)
            ->get();

        return view('storefront.home', ['featured' => $featured]);
    }
}
