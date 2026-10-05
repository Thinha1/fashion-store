<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'totalRevenue' => (string) Order::query()
                ->where('status', 'delivered')
                ->where('payment_status', 'paid')
                ->sum('grand_total'),
            'totalOrders' => Order::query()->count(),
            'totalProducts' => Product::query()->count(),
            'activeProducts' => Product::query()->where('status', 'active')->count(),
            'categoryCount' => Category::query()->where('is_active', true)->count(),
            'lowStockCount' => Product::query()->lowStock()->count(),
            'outOfStockCount' => Product::query()->outOfStock()->count(),
            'withoutImages' => Product::query()->withoutImages()->orderBy('name')->limit(3)->pluck('name'),
            'withoutImagesCount' => Product::query()->withoutImages()->count(),
        ]);
    }
}
