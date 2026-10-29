<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDiscountRequest;
use App\Models\Brand;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\DiscountTargets;
use App\Support\AdminPagination;
use App\Support\AdminSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Variant discounts and vouchers (order and shipping) on one screen: one
 * list with type, status and text filters, one form whose fields follow the
 * chosen type.
 */
class DiscountController extends Controller
{
    public const STATUS_LABELS = [
        'running' => 'Đang chạy',
        'upcoming' => 'Sắp diễn ra',
        'expired' => 'Hết hạn',
        'disabled' => 'Đã tắt',
    ];

    public function index(Request $request): View
    {
        $variant = ProductVariant::query()->whereColumn('product_variants.id', 'discounts.product_variant_id');
        $sorting = new AdminSorting($request, [
            'variant' => [
                (clone $variant)->select('products.name')->join('products', 'products.id', '=', 'product_variants.product_id')->whereNull('products.deleted_at'),
                (clone $variant)->select('size'),
                (clone $variant)->select('color'),
            ],
            'scope' => 'scope', 'discount_value' => 'discount_value', 'used_count' => 'used_count',
            'starts_at' => 'starts_at', 'ends_at' => 'ends_at', 'is_active' => 'is_active',
        ]);

        $filters = [
            'type' => $this->choice($request->query('loai'), array_keys(Discount::SCOPE_LABELS)),
            'status' => $this->choice($request->query('trang-thai'), array_keys(self::STATUS_LABELS)),
            'q' => is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, 100) : '',
        ];

        $discounts = $sorting->apply(Discount::query()
            ->with('productVariant.product:id,name')
            ->when($filters['type'], fn (Builder $query, string $scope) => $query->where('scope', $scope))
            ->when($filters['status'], fn (Builder $query, string $status) => $this->whereStatus($query, $status))
            ->when($filters['q'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $term = '%'.addcslashes($filters['q'], '%_\\').'%';
                $query->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhereHas('productVariant.product', fn (Builder $product) => $product->where('name', 'like', $term));
            }))
            ->orderByDesc('created_at')
            ->orderByDesc('id'))
            ->paginate(AdminPagination::perPage($request))->withQueryString();

        return view('admin.discounts.index', [
            'discounts' => $discounts,
            'sorting' => $sorting,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $scope = $this->choice($request->query('loai'), array_keys(Discount::SCOPE_LABELS)) ?? 'variant';

        return view('admin.discounts.create', [
            'discount' => new Discount([
                'scope' => $scope,
                'discount_type' => 'percent',
                'discount_value' => $scope === 'shipping' ? 100 : 10,
                'is_active' => true,
                'is_auto_apply' => false,
            ]),
            'variants' => collect(),
            'targetOptions' => $this->targetOptions($scope, new Discount),
        ]);
    }

    public function store(StoreDiscountRequest $request): RedirectResponse
    {
        // Picked brands, products and variants: one discount per variant they cover.
        if ($request->isBulkVariant()) {
            $shared = $request->discountData();
            $ids = $request->bulkVariantIds();

            DB::transaction(function () use ($ids, $shared): void {
                foreach ($ids as $id) {
                    Discount::query()->create(['product_variant_id' => $id] + $shared);
                }
            });

            return redirect()->route('admin.discounts.index', ['loai' => 'variant'])
                ->with('status', 'Đã tạo giảm giá cho '.count($ids).' biến thể.');
        }

        $discount = Discount::query()->create($request->discountData());

        return redirect()->route('admin.discounts.index')
            ->with('status', Discount::SCOPE_LABELS[$discount->scope].' đã được tạo.');
    }

    public function edit(Discount $discount): View
    {
        return view('admin.discounts.edit', [
            'discount' => $discount,
            'variants' => $discount->scope === 'variant' ? $this->variants() : collect(),
            'targetOptions' => $this->targetOptions($discount->scope, $discount),
        ]);
    }

    public function update(StoreDiscountRequest $request, Discount $discount): RedirectResponse
    {
        $discount->update($request->discountData());

        return redirect()->route('admin.discounts.index')
            ->with('status', Discount::SCOPE_LABELS[$discount->scope].' đã được cập nhật.');
    }

    public function destroy(Discount $discount): RedirectResponse
    {
        abort_unless(auth()->user()->can('discounts.manage'), 403);

        // Orders keep the amounts they were given; their link to it is cleared.
        $discount->delete();

        return redirect()->route('admin.discounts.index')->with('status', 'Giảm giá đã được xóa.');
    }

    /**
     * @param  Builder<Discount>  $query
     */
    private function whereStatus(Builder $query, string $status): void
    {
        match ($status) {
            'disabled' => $query->where('is_active', false),
            'upcoming' => $query->where('is_active', true)->where('starts_at', '>', now()),
            'expired' => $query->where('is_active', true)->where('ends_at', '<', now()),
            default => $query->currentlyActive(),
        };
    }

    /**
     * @param  list<string>  $allowed
     */
    private function choice(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    private function variants(): Collection
    {
        return ProductVariant::query()->where('is_active', true)->with('product:id,name')->get();
    }

    /**
     * What the brand / product / variant picker offers and has ticked: for a
     * new variant discount (many at once) and for an order voucher (what it is
     * limited to). Null for the kinds that have no picker.
     *
     * @return array{brands: list<array<string, mixed>>, products: list<array<string, mixed>>, variants: list<array<string, mixed>>, selected: array<string, list<int>>}|null
     */
    private function targetOptions(string $scope, Discount $discount): ?array
    {
        if ($scope === 'shipping' || ($scope === 'variant' && $discount->exists)) {
            return null;
        }

        $selected = old('targets') !== null
            ? app(DiscountTargets::class)->normalize(old('targets'))
            : $discount->targetIds();

        $products = Product::query()
            ->where(fn (Builder $query) => $query->where('status', 'active')->orWhereIn('id', $selected['product']))
            ->orderBy('name')->get(['id', 'name', 'brand_id']);

        $variants = ProductVariant::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereIn('id', $selected['variant']))
            ->with('product:id,name,brand_id')->get(['id', 'product_id', 'size', 'color', 'sku'])
            ->sortBy([fn (ProductVariant $a, ProductVariant $b) => strcmp((string) $a->product?->name, (string) $b->product?->name), ['id', 'asc']]);

        return [
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Brand $brand): array => ['id' => $brand->id, 'name' => $brand->name])->all(),
            'products' => $products->map(fn (Product $product): array => ['id' => $product->id, 'name' => $product->name, 'brand' => $product->brand_id])->all(),
            'variants' => $variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'name' => (string) $variant->product?->name,
                'detail' => "{$variant->size} / {$variant->color} ({$variant->sku})",
                'product' => $variant->product_id,
                'brand' => $variant->product?->brand_id,
            ])->values()->all(),
            'selected' => $selected,
        ];
    }
}
