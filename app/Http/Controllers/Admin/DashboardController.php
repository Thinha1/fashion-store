<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        ]);
    }
}
