<?php

namespace App\Services\Ai\Actions;

use App\Http\Requests\Admin\StoreDiscountRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Ai\Actions\Concerns\DescribesAmounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Giảm 20% toàn bộ áo khoác Levents đến hết tháng" → one variant discount
 * per matching variant, found by brand, category (with everything below it),
 * product name, sizes and colors.
 */
class BulkVariantDiscountAction implements AgentAction
{
    use DescribesAmounts;

    private const MAX_VARIANTS = 500;

    public function __construct(private readonly FormRequestRunner $forms) {}

    public function type(): string
    {
        return 'bulk_variant_discount';
    }

    public function permission(): string
    {
        return 'discounts.manage';
    }

    public function promptSpec(): string
    {
        return <<<'SPEC'
            - "bulk_variant_discount": giảm giá hàng loạt biến thể sản phẩm, ví dụ "giảm 20% toàn bộ áo khoác Levents
              đến hết tháng", "sale 15% size XL áo thun nam tuần này". Phải có ít nhất một trong brand, category,
              product_query. Dạng:
              {"type": "bulk_variant_discount",
               "brand": "nguyên văn tên thương hiệu trong danh sách ở trên hoặc \"\"",
               "category": "nguyên văn tên danh mục trong danh sách ở trên hoặc \"\" (gồm cả danh mục con)",
               "product_query": "một phần tên sản phẩm nhân viên nói tới hoặc \"\"",
               "sizes": ["chỉ các size được nhắc tới, hoặc mảng rỗng"],
               "colors": ["chỉ các màu được nhắc tới, hoặc mảng rỗng"],
               "discount_type": "percent" | "fixed", "discount_value": số, "max_discount_amount": số hoặc null,
               "starts_at": "YYYY-MM-DD HH:MM" (mặc định bây giờ), "ends_at": "YYYY-MM-DD HH:MM" (bắt buộc)}
            SPEC;
    }

    public function preview(array $params, User $actor): ActionPreview
    {
        $filters = $this->filters($params);
        $variants = $this->matchingVariants($filters);

        if ($variants->isEmpty()) {
            throw new InvalidActionException('Không có biến thể đang bán nào khớp '.$this->describeFilters($filters).'.');
        }
        if ($variants->count() > self::MAX_VARIANTS) {
            throw new InvalidActionException('Có '.$variants->count().' biến thể khớp, nhiều hơn giới hạn '.self::MAX_VARIANTS.' một lần. Hãy thu hẹp theo danh mục, size hoặc màu.');
        }

        $discount = $this->discountData($params, $variants->first()->id);
        $this->forms->validate(StoreDiscountRequest::class, $discount, $actor);

        $byProduct = $variants->groupBy('product_id');
        $lines = $byProduct->take(8)->map(fn (Collection $group) => $group->first()->product->name.' ('.$group->count().' biến thể)')->values()->all();
        if ($byProduct->count() > 8) {
            $lines[] = 'và '.($byProduct->count() - 8).' sản phẩm khác.';
        }

        $overlapping = Discount::query()->where('scope', 'variant')->currentlyActive()
            ->whereIn('product_variant_id', $variants->modelKeys())->distinct()->count('product_variant_id');

        return new ActionPreview(
            title: 'Giảm '.$this->amount($discount['discount_type'], $discount['discount_value']).' cho '.$variants->count().' biến thể của '.$byProduct->count().' sản phẩm',
            lines: [
                'Khớp '.$this->describeFilters($filters).', từ '.$this->dateTime($discount['starts_at']).' đến '.$this->dateTime($discount['ends_at']).
                ($discount['max_discount_amount'] ? ', giảm tối đa '.$this->money($discount['max_discount_amount']).' mỗi sản phẩm' : '').'.',
                ...$lines,
            ],
            confirmLabel: 'Tạo giảm giá cho '.$variants->count().' biến thể',
            params: ['variant_ids' => $variants->modelKeys(), 'discount' => $discount],
            warnings: $overlapping > 0
                ? ["{$overlapping} biến thể đang có giảm giá khác; khách sẽ được mức giảm cao nhất."]
                : [],
        );
    }

    public function execute(array $params, User $actor): ActionResult
    {
        // Only variants still on sale now; the shared fields are checked again.
        $ids = ProductVariant::query()->whereIn('id', $params['variant_ids'] ?? [])->where('is_active', true)
            ->whereHas('product', fn (Builder $product) => $product->where('status', 'active'))->pluck('id');

        if ($ids->isEmpty()) {
            throw new InvalidActionException('Các biến thể này không còn đang bán.');
        }

        $request = $this->forms->validate(StoreDiscountRequest::class, ['product_variant_id' => $ids->first()] + $params['discount'], $actor);
        $shared = $request->discountData();

        DB::transaction(function () use ($ids, $shared): void {
            foreach ($ids as $id) {
                Discount::query()->create(['product_variant_id' => $id] + $shared);
            }
        });

        return new ActionResult(
            message: 'Đã tạo giảm giá cho '.$ids->count().' biến thể.',
            links: [['label' => 'Xem danh sách giảm giá theo biến thể', 'url' => route('admin.discounts.index', ['loai' => 'variant'])]],
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{brand: ?Brand, category: ?string, category_ids: list<int>, product_query: string, sizes: list<string>, colors: list<string>}
     */
    private function filters(array $params): array
    {
        $brandName = is_string($params['brand'] ?? null) ? trim($params['brand']) : '';
        $categoryName = is_string($params['category'] ?? null) ? trim($params['category']) : '';
        $query = is_string($params['product_query'] ?? null) ? trim($params['product_query']) : '';

        if ($brandName === '' && $categoryName === '' && $query === '') {
            throw new InvalidActionException('Hãy cho biết giảm giá cho thương hiệu, danh mục hoặc sản phẩm nào.');
        }

        $brand = null;
        if ($brandName !== '') {
            $brand = $this->named(Brand::class, $brandName)->first()
                ?? throw new InvalidActionException("Không có thương hiệu \"{$brandName}\".");
        }

        $categoryIds = [];
        if ($categoryName !== '') {
            $roots = $this->named(Category::class, $categoryName)->map(fn (Category $category) => (int) $category->id)->all();
            if ($roots === []) {
                throw new InvalidActionException("Không có danh mục \"{$categoryName}\".");
            }
            $categoryIds = Category::withDescendantIds($roots);
        }

        return [
            'brand' => $brand,
            'category' => $categoryName !== '' ? $categoryName : null,
            'category_ids' => $categoryIds,
            'product_query' => $query,
            'sizes' => array_map('mb_strtoupper', $this->strings($params['sizes'] ?? [])),
            'colors' => $this->strings($params['colors'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ProductVariant>
     */
    private function matchingVariants(array $filters): Collection
    {
        return ProductVariant::query()
            ->where('is_active', true)
            ->when($filters['sizes'], fn (Builder $query, array $sizes) => $query->whereIn('size', $sizes))
            ->when($filters['colors'], fn (Builder $query, array $colors) => $query->whereIn('color', $colors))
            ->whereHas('product', function (Builder $product) use ($filters): void {
                $product->where('status', 'active')
                    ->when($filters['brand'], fn (Builder $query, Brand $brand) => $query->where('brand_id', $brand->id))
                    ->when($filters['category_ids'], fn (Builder $query, array $ids) => $query->whereIn('category_id', $ids))
                    ->when($filters['product_query'] !== '', fn (Builder $query) => $query->where('name', 'like', '%'.addcslashes($filters['product_query'], '%_\\').'%'));
            })
            ->with('product:id,name')
            ->orderBy('product_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function describeFilters(array $filters): string
    {
        return implode(', ', array_filter([
            $filters['brand'] ? 'thương hiệu '.$filters['brand']->name : null,
            $filters['category'] ? 'danh mục '.$filters['category'] : null,
            $filters['product_query'] !== '' ? 'tên có "'.$filters['product_query'].'"' : null,
            $filters['sizes'] ? 'size '.implode(', ', $filters['sizes']) : null,
            $filters['colors'] ? 'màu '.implode(', ', $filters['colors']) : null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function discountData(array $params, int $variantId): array
    {
        $max = $this->number($params['max_discount_amount'] ?? null);

        return [
            'scope' => 'variant',
            'product_variant_id' => $variantId,
            'discount_type' => ($params['discount_type'] ?? '') === 'fixed' ? 'fixed' : 'percent',
            'discount_value' => $this->number($params['discount_value'] ?? null) ?? '',
            'max_discount_amount' => $max === null ? null : (int) round($max),
            'starts_at' => $this->moment(($params['starts_at'] ?? '') ?: now()->format('Y-m-d H:i'), 'ngày bắt đầu'),
            'ends_at' => $this->moment($params['ends_at'] ?? null, 'ngày kết thúc', endOfDay: true),
            'is_active' => 1,
        ];
    }
}
