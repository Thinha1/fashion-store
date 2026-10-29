<?php

namespace App\Mcp\Tools;

use App\Models\Order;
use App\Services\Reports\SalesDashboard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class LatestOrdersTool extends DashboardTool
{
    private const DEFAULT_LIMIT = 6;

    private const MAX_LIMIT = 20;

    protected string $name = 'latest_orders';

    protected string $title = 'Đơn hàng mới nhất';

    protected string $description = 'Các đơn đặt gần nhất: mã đơn, tên khách (đã viết tắt để bảo vệ thông tin cá nhân), trạng thái, thanh toán, tổng tiền (VND) và thời điểm đặt. Không có số điện thoại, email hay địa chỉ.';

    protected function permissions(): array
    {
        return ['dashboard.view', 'orders.view'];
    }

    public function schema(JsonSchema $schema): array
    {
        return ['limit' => $this->limitSchema($schema, self::DEFAULT_LIMIT, self::MAX_LIMIT)];
    }

    protected function data(Request $request, SalesDashboard $dashboard): array
    {
        $orders = $dashboard->latestOrders($this->limit($request, self::DEFAULT_LIMIT, self::MAX_LIMIT));

        return [
            'currency' => 'VND',
            'orders' => $orders->map(fn (Order $order): array => [
                'order_number' => $order->order_number,
                'customer' => self::maskName((string) $order->customer_name),
                'status' => $order->statusLabel(),
                'payment_status' => $order->paymentStatusLabel(),
                'grand_total' => (int) round((float) $order->grand_total),
                'placed_at' => $order->placed_at ? $this->time($order->placed_at) : null,
            ])->all(),
        ];
    }

    /**
     * "Nguyễn Văn An" → "N. V. An": the given name stays, the rest become
     * initials.
     */
    public static function maskName(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return 'Khách';
        }

        $given = array_pop($words);
        $initials = array_map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)).'.', $words);

        return implode(' ', [...$initials, $given]);
    }
}
