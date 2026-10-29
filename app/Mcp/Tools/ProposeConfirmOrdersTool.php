<?php

namespace App\Mcp\Tools;

use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ProposeConfirmOrdersTool extends ProposeActionTool
{
    protected string $name = 'propose_confirm_orders';

    protected string $title = 'Đề xuất xác nhận đơn hàng loạt';

    protected string $description = 'Chọn các đơn đang "Chờ xác nhận" để xác nhận hàng loạt: theo mã đơn, cách thanh toán, khoảng giờ đặt hoặc tổng tiền tối đa; không điền gì là mọi đơn chờ xác nhận. Đơn chuyển khoản chưa nhận tiền luôn bị bỏ qua. Tối đa 50 đơn mỗi lần, cũ nhất trước.'.self::TWO_STEP;

    protected function type(): string
    {
        return 'confirm_orders';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_numbers' => $schema->array()->items($schema->string())->description('Mã các đơn cần xác nhận nếu người dùng nêu cụ thể; bỏ trống để chọn theo điều kiện khác.'),
            'payment_method' => $schema->string()->enum(array_keys(Order::PAYMENT_METHOD_LABELS))->description('Chỉ xác nhận đơn theo cách thanh toán này: cod hoặc bank_transfer. Bỏ trống cho mọi cách.'),
            'placed_from' => $this->momentSchema($schema, 'Chỉ lấy đơn đặt từ thời điểm này'),
            'placed_to' => $this->momentSchema($schema, 'Chỉ lấy đơn đặt đến thời điểm này'),
            'max_total' => $schema->number()->min(0)->description('Chỉ lấy đơn có tổng tiền không quá số này (VND).'),
        ];
    }
}
