<?php

namespace App\Mcp\Tools;

use App\Services\Reports\SalesDashboard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SalesSeriesTool extends DashboardTool
{
    protected string $name = 'sales_series';

    protected string $title = 'Doanh số theo thời gian';

    protected string $description = 'Doanh số (mọi đơn không bị huỷ, VND) và số đơn theo từng giờ (hôm nay), từng ngày (7/30/90 ngày) hoặc từng tháng (toàn thời gian), đủ mọi mốc kể cả mốc bằng 0 (biểu đồ trên Dashboard). Dùng để xem xu hướng và ngày cao điểm.';

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
            'granularity' => $period->granularity(),
            'currency' => 'VND',
            'points' => array_map(fn (array $point): array => [
                'label' => $point['longLabel'],
                'sales' => $point['sales'],
                'orders' => $point['orders'],
            ], $dashboard->salesSeries($period)),
        ];
    }
}
