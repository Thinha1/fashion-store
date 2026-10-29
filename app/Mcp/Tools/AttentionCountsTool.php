<?php

namespace App\Mcp\Tools;

use App\Services\Reports\SalesDashboard;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class AttentionCountsTool extends DashboardTool
{
    protected string $name = 'attention_counts';

    protected string $title = 'Việc cần xử lý';

    protected string $description = 'Những việc đang chờ xử lý ngay lúc này, không phụ thuộc khoảng thời gian: số đơn chờ xác nhận, số biến thể hết hàng và số biến thể sắp hết hàng (tồn kho bằng hoặc dưới ngưỡng cảnh báo, gồm cả hết hàng).';

    protected function permissions(): array
    {
        return ['dashboard.view'];
    }

    protected function data(Request $request, SalesDashboard $dashboard): array
    {
        $counts = $dashboard->actionCounts();

        return [
            'pending_orders' => $counts['pendingOrders'],
            'out_of_stock_variants' => $counts['outOfStock'],
            'low_stock_variants' => $counts['lowStock'],
        ];
    }
}
