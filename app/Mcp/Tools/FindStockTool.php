<?php

namespace App\Mcp\Tools;

use App\Models\ProductVariant;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class FindStockTool extends ShopTool
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 50;

    protected string $name = 'find_stock';

    protected string $title = 'Tra cứu tồn kho';

    protected string $description = 'Tìm biến thể theo một phần tên sản phẩm hoặc SKU (có thể thu hẹp theo thương hiệu): sản phẩm, thương hiệu, SKU, size, màu, tồn kho, ngưỡng cảnh báo, on_sale (còn mở bán) và low (tồn kho bằng hoặc dưới ngưỡng). total_matches là tổng số biến thể khớp, kể cả những dòng vượt quá limit. Dùng khi khách hỏi "còn size M màu đen không".';

    protected function permissions(): array
    {
        return ['dashboard.view', 'inventory.manage'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->min(2)->max(100)->required()->description('Một phần tên sản phẩm hoặc SKU, ví dụ "áo khoác" hoặc "CM-AT-001".'),
            'brand' => $schema->string()->max(100)->description('Một phần tên thương hiệu để thu hẹp kết quả. Bỏ trống nếu không cần.'),
            'limit' => $this->limitSchema($schema, self::DEFAULT_LIMIT, self::MAX_LIMIT),
        ];
    }

    public function handle(Request $request, AuditLogger $audit): ResponseFactory
    {
        $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
        ]);
        $limit = $this->limit($request, self::DEFAULT_LIMIT, self::MAX_LIMIT);
        $like = fn (string $text): string => '%'.addcslashes(trim($text), '%_\\').'%';
        $text = $like((string) $request->get('query'));
        $brand = trim((string) $request->get('brand'));

        $matching = fn (): Builder => ProductVariant::query()
            ->where(fn (Builder $query) => $query->where('sku', 'like', $text)
                ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', $text)))
            ->when($brand !== '', fn (Builder $query) => $query->whereHas('product.brand', fn (Builder $q) => $q->where('name', 'like', $like($brand))));

        $variants = $matching()->with('product.brand')->orderBy('product_id')->orderBy('id')->limit($limit)->get();

        $this->audit($audit, $request);

        return Response::structured([
            'total_matches' => $matching()->count(),
            'variants' => $variants->map(fn (ProductVariant $variant): array => [
                'product' => $variant->product?->name,
                'brand' => $variant->product?->brand?->name,
                'sku' => $variant->sku,
                'size' => $variant->size,
                'color' => $variant->color,
                'stock' => (int) $variant->stock_quantity,
                'threshold' => (int) $variant->low_stock_threshold,
                'on_sale' => $variant->is_active && $variant->product?->status === 'active',
                'low' => $variant->stock_quantity <= $variant->low_stock_threshold,
            ])->all(),
        ]);
    }
}
