<?php

namespace App\Mcp\Tools;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetOrderTool extends ShopTool
{
    protected string $name = 'get_order';

    protected string $title = 'Chi tiết đơn hàng';

    protected string $description = 'Chi tiết một đơn theo mã đơn (ví dụ DH261024YZEBRU): trạng thái, thanh toán, các dòng hàng (sản phẩm, SKU, size, màu, số lượng, giá), các khoản tiền (VND), tỉnh/thành giao hàng và lịch sử đổi trạng thái. Tên khách được viết tắt; không có số điện thoại, email, địa chỉ chi tiết hay ghi chú của khách.';

    protected function permissions(): array
    {
        return ['orders.view', 'orders.manage'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_number' => $schema->string()->min(3)->max(40)->required()->description('Mã đơn hàng, ví dụ DH261024YZEBRU.'),
        ];
    }

    public function handle(Request $request, AuditLogger $audit): ResponseFactory|Response
    {
        $request->validate(['order_number' => ['required', 'string', 'min:3', 'max:40']]);
        $number = mb_strtoupper(trim((string) $request->get('order_number')));

        $order = Order::query()->with('items')->where('order_number', $number)->first();
        $this->audit($audit, $request);

        if ($order === null) {
            return Response::error("Không có đơn hàng mã {$number}.");
        }

        $money = fn (mixed $amount): int => (int) round((float) $amount);

        return Response::structured([
            'currency' => 'VND',
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => $order->statusLabel(),
            'payment_method' => $order->paymentMethodLabel(),
            'payment_status' => $order->paymentStatusLabel(),
            'placed_at' => $order->placed_at ? $this->time($order->placed_at) : null,
            'customer' => LatestOrdersTool::maskName((string) $order->customer_name),
            'province' => $order->province_name,
            'subtotal' => $money($order->subtotal),
            'discount_amount' => $money($order->discount_amount),
            'shipping_fee' => $money($order->shipping_fee),
            'shipping_discount_amount' => $money($order->shipping_discount_amount),
            'grand_total' => $money($order->grand_total),
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'product' => $item->product_name,
                'sku' => $item->sku,
                'size' => $item->size_name,
                'color' => $item->color_name,
                'quantity' => (int) $item->quantity,
                'unit_price' => $money($item->unit_price),
                'line_total' => $money($item->line_total),
            ])->all(),
            'history' => collect($order->status_history ?? [])->map(fn (array $step): array => [
                'from' => Order::STATUS_LABELS[$step['from'] ?? ''] ?? ($step['from'] ?? null),
                'to' => Order::STATUS_LABELS[$step['to'] ?? ''] ?? ($step['to'] ?? null),
                'at' => $step['at'] ?? null,
                'note' => $step['note'] ?? null,
            ])->all(),
        ]);
    }
}
