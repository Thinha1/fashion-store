<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ProposeBulkVariantDiscountTool extends ProposeActionTool
{
    protected string $name = 'propose_bulk_variant_discount';

    protected string $title = 'Đề xuất giảm giá hàng loạt';

    protected string $description = 'Giảm giá hàng loạt các biến thể đang bán, tìm theo thương hiệu, danh mục (gồm cả danh mục con), tên sản phẩm, size và màu. Phải có ít nhất một trong brand, category, product_query. Mỗi biến thể một giảm giá, tối đa 500 biến thể mỗi lần. Ngày kết thúc là bắt buộc; thiếu thì hỏi người dùng.'.self::TWO_STEP;

    protected function type(): string
    {
        return 'bulk_variant_discount';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'brand' => $schema->string()->description('Tên thương hiệu. Bỏ trống nếu không lọc theo thương hiệu.'),
            'category' => $schema->string()->description('Tên danh mục (gồm cả danh mục con). Bỏ trống nếu không lọc theo danh mục.'),
            'product_query' => $schema->string()->description('Một phần tên sản phẩm. Bỏ trống nếu không lọc theo tên.'),
            'sizes' => $schema->array()->items($schema->string())->description('Chỉ các size được nhắc tới, ví dụ ["XL"]. Bỏ trống nếu mọi size.'),
            'colors' => $schema->array()->items($schema->string())->description('Chỉ các màu được nhắc tới. Bỏ trống nếu mọi màu.'),
            'discount_type' => $schema->string()->enum(['percent', 'fixed'])->required()->description('percent: giảm theo phần trăm; fixed: giảm số tiền cố định.'),
            'discount_value' => $schema->number()->min(0)->required()->description('Phần trăm (ví dụ 20) hoặc số tiền VND.'),
            'max_discount_amount' => $schema->number()->min(0)->description('Số tiền giảm tối đa (VND) mỗi sản phẩm, nếu có.'),
            'starts_at' => $this->momentSchema($schema, 'Bắt đầu (mặc định là bây giờ)'),
            'ends_at' => $this->momentSchema($schema, 'Kết thúc')->required(),
        ];
    }
}
