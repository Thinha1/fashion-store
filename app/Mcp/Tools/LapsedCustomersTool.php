<?php

namespace App\Mcp\Tools;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\CustomerStats;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class LapsedCustomersTool extends CustomerTool
{
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 20;

    private const DEFAULT_MIN_ORDERS = 2;

    private const DEFAULT_DAYS = 60;

    protected string $name = 'lapsed_customers';

    protected string $title = 'Khách lâu không quay lại';

    protected string $description = 'Khách đã mua nhiều lần (từ min_orders đơn đã giao và đã thanh toán, mặc định 2) nhưng chưa đặt đơn nào trong days ngày gần đây (mặc định 60), chi nhiều nhất lên trước: id, tên viết tắt, 3 số cuối điện thoại, số đơn đã giao, đã chi (VND), đơn gần nhất, số ngày từ đơn gần nhất và admin_url. Dùng để kéo khách cũ quay lại.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'min_orders' => $schema->integer()->min(1)->max(50)->description('Số đơn đã giao và đã thanh toán tối thiểu để coi là khách đã mua nhiều lần (mặc định '.self::DEFAULT_MIN_ORDERS.').'),
            'days' => $schema->integer()->min(1)->max(730)->description('Số ngày gần đây không có đơn nào (mặc định '.self::DEFAULT_DAYS.').'),
            'limit' => $this->limitSchema($schema, self::DEFAULT_LIMIT, self::MAX_LIMIT),
        ];
    }

    public function handle(Request $request, AuditLogger $audit): ResponseFactory|Response
    {
        $request->validate([
            'min_orders' => ['nullable', 'integer', 'min:1', 'max:50'],
            'days' => ['nullable', 'integer', 'min:1', 'max:730'],
        ]);
        $minOrders = (int) ($request->get('min_orders') ?? self::DEFAULT_MIN_ORDERS);
        $days = (int) ($request->get('days') ?? self::DEFAULT_DAYS);
        $limit = $this->limit($request, self::DEFAULT_LIMIT, self::MAX_LIMIT);

        $customers = CustomerStats::customers()
            ->withCount(['orders as delivered_orders' => fn (Builder $orders) => CustomerStats::spent($orders)])
            ->whereHas('orders', fn (Builder $orders) => CustomerStats::spent($orders), '>=', $minOrders)
            ->whereDoesntHave('orders', fn (Builder $orders) => $orders->where('placed_at', '>=', now()->subDays($days)))
            ->orderByDesc('spent')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $this->audit($audit, $request);

        return Response::structured([
            'currency' => 'VND',
            'min_orders' => $minOrders,
            'days' => $days,
            'customers' => $customers->map(function (User $customer): array {
                $last = Carbon::parse($customer->last_order_at);

                return [
                    ...$this->identify($customer),
                    'delivered_orders' => (int) $customer->delivered_orders,
                    'spent' => $this->money($customer->spent),
                    'last_order_at' => $this->time($last),
                    'days_since_last_order' => (int) $last->diffInDays(now()),
                ];
            })->all(),
        ]);
    }
}
