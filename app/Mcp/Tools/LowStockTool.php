<?php

namespace App\Mcp\Tools;

use App\Models\ProductVariant;
use App\Services\Reports\SalesDashboard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class LowStockTool extends DashboardTool
{
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

    protected string $name = 'low_stock';

    protected string $title = 'Hàng sắp hết';

    protected string $description = 'Các biến thể đang bán có tồn kho bằng hoặc dưới ngưỡng cảnh báo của chính nó, ít hàng nhất lên trước: sản phẩm, SKU, size, màu, tồn kho, ngưỡng. total_low_stock là tổng số biến thể sắp hết, kể cả những dòng vượt quá limit.';

    protected function permissions(): array
    {
        return ['dashboard.view', 'inventory.manage'];
    }

    public function schema(JsonSchema $schema): array
    {
        return ['limit' => $this->limitSchema($schema, self::DEFAULT_LIMIT, self::MAX_LIMIT)];
    }

    protected function data(Request $request, SalesDashboard $dashboard): array
    {
        $variants = $dashboard->lowStockVariants($this->limit($request, self::DEFAULT_LIMIT, self::MAX_LIMIT));

        return [
            'total_low_stock' => $dashboard->actionCounts()['lowStock'],
            'variants' => $variants->map(fn (ProductVariant $variant): array => [
                'product' => $variant->product?->name,
                'sku' => $variant->sku,
                'size' => $variant->size,
                'color' => $variant->color,
                'stock' => (int) $variant->stock_quantity,
                'threshold' => (int) $variant->low_stock_threshold,
            ])->all(),
        ];
    }
}
