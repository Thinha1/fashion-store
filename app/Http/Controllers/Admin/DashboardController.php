<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Reports\DashboardPeriod;
use App\Services\Reports\SalesDashboard;
use App\Support\AdminNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Sales overview for one period (`?khoang=`, default last 30 days), plus
     * the things waiting for staff right now.
     */
    public function __invoke(Request $request, SalesDashboard $dashboard): View|RedirectResponse
    {
        // Revenue is for dashboard.view only; other staff land on their first page.
        if (! $request->user()->hasPermission('dashboard.view')) {
            $landing = AdminNavigation::landingRoute($request->user());

            return $landing === null ? view('admin.no-access') : redirect()->route($landing);
        }

        $period = DashboardPeriod::fromKey($request->query('khoang'));
        $previousPeriod = $period->previous();
        $totals = $dashboard->totals($period);
        $previousTotals = $previousPeriod ? $dashboard->totals($previousPeriod) : null;

        return view('admin.dashboard', [
            'period' => $period,
            'totalRevenue' => $totals['revenue'],
            'totalOrders' => $totals['orders'],
            'totalProducts' => Product::query()->count(),
            'totals' => $totals,
            'changes' => $previousTotals === null ? [] : [
                'revenue' => $dashboard->change($totals['revenue'], $previousTotals['revenue']),
                'orders' => $dashboard->change($totals['orders'], $previousTotals['orders']),
                'averageOrderValue' => $dashboard->change($totals['averageOrderValue'], $previousTotals['averageOrderValue']),
            ],
            'salesSeries' => $dashboard->salesSeries($period),
            'topProducts' => $dashboard->topProducts($period),
            'statusBreakdown' => $dashboard->statusBreakdown($period),
            'lowStockVariants' => $dashboard->lowStockVariants(),
            'actionCounts' => $dashboard->actionCounts(),
            'latestOrders' => $dashboard->latestOrders(),
        ]);
    }
}
