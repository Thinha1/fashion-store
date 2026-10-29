<?php

namespace App\Mcp\Tools;

use App\Services\Reports\SalesDashboard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class TopProductsTool extends DashboardTool
{
    private const DEFAULT_LIMIT = 5;

    private const MAX_LIMIT = 20;

    protected string $name = 'top_products';

    protected string $title = 'Sản phẩm bán chạy';

    protected string $description = 'Sản phẩm bán chạy nhất trong khoảng thời gian, xếp theo số lượng bán rồi doanh số (VND), tính trên các đơn không bị huỷ.';

    protected function permissions(): array
    {
        return ['dashboard.view'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $this->periodSchema($schema),
            'limit' => $this->limitSchema($schema, self::DEFAULT_LIMIT, self::MAX_LIMIT),
        ];
    }

    protected function data(Request $request, SalesDashboard $dashboard): array
    {
        $period = $this->period($request);

        return [
            'period' => $this->describePeriod($period),
            'currency' => 'VND',
            'products' => $dashboard->topProducts($period, $this->limit($request, self::DEFAULT_LIMIT, self::MAX_LIMIT)),
        ];
    }
}
