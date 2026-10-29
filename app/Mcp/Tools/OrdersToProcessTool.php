<?php

namespace App\Mcp\Tools;

use App\Models\Order;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class OrdersToProcessTool extends ShopTool
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 50;

    protected string $name = 'orders_to_process';

    protected string $title = 'Đơn cần xử lý';

    protected string $description = 'Các đơn đang "Chờ xác nhận", cũ nhất trước: mã đơn, tên khách (đã viết tắt), cách thanh toán, tổng tiền (VND), đã chờ bao nhiêu giờ và can_confirm (xác nhận được ngay: đơn COD hoặc đơn chuyển khoản đã nhận tiền). Đơn chuyển khoản chưa nhận tiền chưa xác nhận được. Kèm số đơn xác nhận được ngay và số đơn còn chờ tiền. Muốn xác nhận hàng loạt thì dùng propose_confirm_orders.';

    protected function permissions(): array
    {
        return ['orders.view', 'orders.manage'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'payment_method' => $schema->string()->enum(array_keys(Order::PAYMENT_METHOD_LABELS))->description('Chỉ lấy đơn theo cách thanh toán: cod hoặc bank_transfer. Bỏ trống để lấy tất cả.'),
            'limit' => $this->limitSchema($schema, self::DEFAULT_LIMIT, self::MAX_LIMIT),
        ];
    }

    public function handle(Request $request, AuditLogger $audit): ResponseFactory
    {
        $request->validate(['payment_method' => ['nullable', 'string', Rule::in(array_keys(Order::PAYMENT_METHOD_LABELS))]]);
        $method = $request->get('payment_method');
        $limit = $this->limit($request, self::DEFAULT_LIMIT, self::MAX_LIMIT);

        $pending = fn (): Builder => Order::query()->where('status', 'pending')->when($method, fn (Builder $query) => $query->where('payment_method', $method));
        $total = $pending()->count();
        $ready = $pending()->where(fn (Builder $query) => $query->where('payment_method', 'cod')->orWhere('payment_status', 'paid'))->count();

        $orders = $pending()->orderBy('placed_at')->orderBy('id')->limit($limit)->get();

        $this->audit($audit, $request);

        return Response::structured([
            'currency' => 'VND',
            'pending_total' => $total,
            'ready_to_confirm' => $ready,
            'waiting_for_payment' => $total - $ready,
            'orders' => $orders->map(fn (Order $order): array => [
                'order_number' => $order->order_number,
                'customer' => LatestOrdersTool::maskName((string) $order->customer_name),
                'payment_method' => $order->payment_method,
                'payment_status' => $order->paymentStatusLabel(),
                'grand_total' => (int) round((float) $order->grand_total),
                'placed_at' => $order->placed_at ? $this->time($order->placed_at) : null,
                'hours_waiting' => $order->placed_at ? max(0, (int) floor($order->placed_at->diffInHours(now()))) : null,
                'can_confirm' => $order->payment_method === 'cod' || $order->payment_status === 'paid',
            ])->all(),
        ]);
    }
}
