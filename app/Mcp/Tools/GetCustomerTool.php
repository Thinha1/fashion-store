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
class GetCustomerTool extends CustomerTool
{
    private const RECENT_ORDERS = 10;

    protected string $name = 'get_customer';

    protected string $title = 'Chi tiết khách hàng';

    protected string $description = 'Một khách hàng theo id (lấy từ find_customer): số đơn, đã chi (VND, đơn đã giao và đã thanh toán), giá trị trung bình mỗi đơn đã giao, số đơn đã huỷ và tỷ lệ huỷ, 10 đơn gần nhất (mã đơn, trạng thái, thanh toán, tổng tiền, ngày đặt), số địa chỉ đã lưu, các tỉnh/thành đã giao và admin_url. Tên được viết tắt; không có email, số điện thoại đầy đủ hay địa chỉ chi tiết.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->min(1)->required()->description('id của khách hàng, lấy từ find_customer hoặc top_customers.'),
        ];
    }

    public function handle(Request $request, AuditLogger $audit): ResponseFactory|Response
    {
        $request->validate(['customer_id' => ['required', 'integer', 'min:1']]);
        $id = (int) $request->get('customer_id');

        $customer = User::query()->customers()->find($id);
        $this->audit($audit, $request);

        if ($customer === null) {
            return Response::error("Không có khách hàng id {$id}.");
        }

        $orders = fn () => Order::query()->where('user_id', $customer->id);
        $spent = fn () => $orders()->tap(CustomerStats::spent(...));
        $total = $orders()->count();
        $cancelled = $orders()->where('status', 'cancelled')->count();

        return Response::structured([
            'currency' => 'VND',
            ...$this->identify($customer),
            'registered_at' => $this->time($customer->created_at),
            'orders' => $total,
            'delivered_orders' => $spent()->count(),
            'spent' => $this->money($spent()->sum('grand_total')),
            'average_order_value' => $this->money($spent()->avg('grand_total')),
            'cancelled_orders' => $cancelled,
            'cancel_rate_percent' => $total === 0 ? 0 : round($cancelled * 100 / $total, 1),
            'last_order_at' => ($last = $orders()->max('placed_at')) ? $this->time(new \DateTimeImmutable($last)) : null,
            'saved_addresses' => $customer->addresses()->count(),
            'shipped_to_provinces' => $orders()->whereNotNull('province_name')->distinct()->orderBy('province_name')->limit(10)->pluck('province_name')->all(),
            'recent_orders' => $orders()->orderByDesc('placed_at')->orderByDesc('id')->limit(self::RECENT_ORDERS)->get()
                ->map(fn (Order $order): array => [
                    'order_number' => $order->order_number,
                    'status' => $order->statusLabel(),
                    'payment_status' => $order->paymentStatusLabel(),
                    'grand_total' => $this->money($order->grand_total),
                    'placed_at' => $order->placed_at ? $this->time($order->placed_at) : null,
                ])->all(),
        ]);
    }
}
