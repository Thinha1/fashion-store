<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ProposeCreateVouchersTool extends ProposeActionTool
{
    protected string $name = 'propose_create_vouchers';

    protected string $title = 'Đề xuất tạo voucher';

    protected string $description = 'Tạo voucher giảm tiền hàng hoặc giảm phí vận chuyển (freeship là giảm phí vận chuyển 100%), tối đa 10 voucher mỗi lần. Voucher đi qua đúng quy tắc của form tạo giảm giá trong trang quản trị. Ngày kết thúc là bắt buộc; thiếu thì hỏi người dùng.'.self::TWO_STEP;

    protected function type(): string
    {
        return 'create_vouchers';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'vouchers' => $schema->array()->min(1)->max(10)->required()->items($schema->object([
                'scope' => $schema->string()->enum(['order', 'shipping'])->required()->description('order: giảm tiền hàng; shipping: giảm phí vận chuyển.'),
                'name' => $schema->string()->required()->description('Tên ngắn mô tả ưu đãi, ví dụ "Freeship đơn từ 500.000 ₫".'),
                'code' => $schema->string()->description('Mã viết hoa không dấu, chỉ chữ, số, gạch ngang và gạch dưới. Để trống nếu voucher tự động áp dụng và không có mã.'),
                'is_auto_apply' => $schema->boolean()->description('true nếu tự động áp dụng, khách không cần nhập mã.'),
                'discount_type' => $schema->string()->enum(['percent', 'fixed'])->required()->description('percent: giảm theo phần trăm; fixed: giảm số tiền cố định.'),
                'discount_value' => $schema->number()->min(0)->required()->description('Phần trăm (ví dụ 20) hoặc số tiền VND.'),
                'max_discount_amount' => $schema->number()->min(0)->description('Số tiền giảm tối đa (VND), nếu có.'),
                'min_order_amount' => $schema->number()->min(0)->description('Giá trị đơn tối thiểu (VND), nếu có.'),
                'usage_limit' => $schema->integer()->min(1)->description('Tổng số lượt dùng, nếu có giới hạn.'),
                'usage_limit_per_customer' => $schema->integer()->min(1)->description('Số lượt dùng mỗi khách, nếu có giới hạn.'),
                'starts_at' => $this->momentSchema($schema, 'Bắt đầu (mặc định là bây giờ)'),
                'ends_at' => $this->momentSchema($schema, 'Kết thúc')->required(),
            ])),
        ];
    }
}
