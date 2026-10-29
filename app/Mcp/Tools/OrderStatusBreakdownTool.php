<?php

namespace App\Mcp\Tools;

use App\Services\Reports\SalesDashboard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class OrderStatusBreakdownTool extends DashboardTool
{
    protected string $name = 'order_status_breakdown';

    protected string $title = 'Đơn hàng theo trạng thái';

    protected string $description = 'Số đơn đặt trong khoảng thời gian, chia theo trạng thái: các bước xử lý (chờ xác nhận đến đã giao, in_pipeline = true) rồi đã huỷ và đã trả hàng.';

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

        return [
            'period' => $this->describePeriod($period),
            'statuses' => array_map(fn (array $row): array => [
                'status' => $row['status'],
                'label' => $row['label'],
                'count' => $row['count'],
                'in_pipeline' => $row['inPipeline'],
            ], $dashboard->statusBreakdown($period)),
        ];
    }
}
