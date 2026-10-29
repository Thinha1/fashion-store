<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ProposeDraftGoodsReceiptsTool extends ProposeActionTool
{
    protected string $name = 'propose_draft_goods_receipts';

    protected string $title = 'Đề xuất soạn phiếu nhập nháp';

    protected string $description = 'Soạn phiếu nhập NHÁP cho các biến thể sắp hết hàng (tồn kho bằng hoặc dưới ngưỡng cảnh báo), mỗi nhà cung cấp một phiếu. Mỗi biến thể đi theo nhà cung cấp và giá của lần nhập gần nhất, hoặc nhà cung cấp người dùng chỉ định. Phiếu nháp chưa làm tăng tồn kho; xác nhận phiếu vẫn làm trong trang quản trị. Tối đa 200 biến thể mỗi lần.'.self::TWO_STEP;

    protected function type(): string
    {
        return 'draft_goods_receipts';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'supplier' => $schema->string()->description('Tên nhà cung cấp nếu người dùng chỉ định; bỏ trống để dùng nhà cung cấp của lần nhập gần nhất.'),
            'brand' => $schema->string()->description('Tên thương hiệu để chỉ soạn cho hàng của thương hiệu đó. Bỏ trống nếu không cần.'),
            'product_query' => $schema->string()->description('Một phần tên sản phẩm để thu hẹp. Bỏ trống nếu không cần.'),
            'quantity' => $schema->integer()->min(1)->description('Số lượng nhập mỗi biến thể nếu người dùng nói; bỏ trống để tự tính (đủ để tồn kho lên gấp đôi ngưỡng).'),
        ];
    }
}
