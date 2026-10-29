<?php

namespace App\Mcp\Tools;

use App\Models\Order;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\CustomerStats;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class TopCustomersTool extends CustomerTool
{
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 20;

    protected string $name = 'top_customers';

    protected string $title = 'Khách chi nhiều nhất';

    protected string $description = 'Khách hàng chi nhiều nhất trong khoảng thời gian, tính trên đơn đặt trong khoảng đó đã giao và đã thanh toán (VND): id, tên viết tắt, 3 số cuối điện thoại, số đơn đã giao, đã chi và admin_url. Dùng để chọn khách tặng voucher hoặc chăm sóc riêng.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $this->periodSchema($schema),
            'limit' => $this->limitSchema($schema, self::DEFAULT_LIMIT, self::MAX_LIMIT),
        ];
    }

    public function handle(Request $request, AuditLogger $audit): ResponseFactory|Response
    {
        $period = $this->period($request);
        $limit = $this->limit($request, self::DEFAULT_LIMIT, self::MAX_LIMIT);

        $totals = $period->apply(Order::query()->tap(CustomerStats::spent(...)), 'placed_at')
            ->whereIn('user_id', User::query()->customers()->select('users.id'))
            ->selectRaw('user_id, count(*) as delivered_orders, sum(grand_total) as spent')
            ->groupBy('user_id')
            ->orderByDesc('spent')
            ->orderBy('user_id')
            ->limit($limit)
            ->get();
        $customers = User::query()->whereKey($totals->pluck('user_id'))->get()->keyBy('id');

        $this->audit($audit, $request);

        return Response::structured([
            'period' => $this->describePeriod($period),
            'currency' => 'VND',
            'customers' => $totals->map(fn (Order $row): array => [
                ...$this->identify($customers[$row->user_id]),
                'delivered_orders' => (int) $row->delivered_orders,
                'spent' => $this->money($row->spent),
            ])->all(),
        ]);
    }
}
