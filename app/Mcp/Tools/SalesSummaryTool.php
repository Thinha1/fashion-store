<?php

namespace App\Mcp\Tools;

use App\Services\Reports\SalesDashboard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SalesSummaryTool extends DashboardTool
{
    protected string $name = 'sales_summary';

    protected string $title = 'Tổng quan doanh số';

    protected string $description = 'Doanh thu, doanh số, số đơn và giá trị đơn trung bình trong một khoảng thời gian, kèm phần trăm thay đổi so với khoảng liền trước (các thẻ số liệu đầu trang Dashboard). Doanh thu (revenue) chỉ tính đơn đã giao và đã thanh toán; doanh số (sales) tính mọi đơn không bị huỷ. Tiền tính bằng VND.';

    protected function permissions(): array
    {
        return ['dashboard.view'];
    }

    public function schema(JsonSchema $schema): array
    {
        return ['period' => $this->periodSchema($schema)];
    }

    protected function data(Request $request, SalesDashboard $dashboard): array
    {
        $period = $this->period($request);
        $totals = $dashboard->totals($period);
        $previous = $period->previous();
        $before = $previous ? $dashboard->totals($previous) : null;

        return [
            'period' => $this->describePeriod($period),
            'currency' => 'VND',
            'revenue' => (int) round((float) $totals['revenue']),
            'sales' => $totals['sales'],
            'orders' => $totals['orders'],
            'cancelled_orders' => $totals['cancelledOrders'],
            'average_order_value' => $totals['averageOrderValue'],
            'change_percent' => $before === null ? null : [
                'compared_with' => $period->comparisonLabel(),
                'revenue' => $dashboard->change($totals['revenue'], $before['revenue']),
                'sales' => $dashboard->change($totals['sales'], $before['sales']),
                'orders' => $dashboard->change($totals['orders'], $before['orders']),
                'average_order_value' => $dashboard->change($totals['averageOrderValue'], $before['averageOrderValue']),
            ],
        ];
    }
}
