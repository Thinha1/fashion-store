<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Numbers for the admin dashboard, all for one DashboardPeriod (by order
 * `placed_at`). Two money measures, kept apart on purpose:
 *  - revenue: delivered AND paid orders — money actually in (as before);
 *  - sales: every order that wasn't cancelled — what customers ordered.
 * Amounts are whole VND.
 */
class SalesDashboard
{
    /**
     * Orders flowing through the shop, in pipeline order; cancelled and
     * returned sit outside the flow.
     */
    public const PIPELINE = ['pending', 'confirmed', 'preparing', 'shipping', 'delivered'];

    /**
     * @return array{revenue: string, orders: int, cancelledOrders: int, sales: int, averageOrderValue: int}
     */
    public function totals(DashboardPeriod $period): array
    {
        $orders = fn (): Builder => $period->apply(Order::query());

        $orderCount = $orders()->count();
        $cancelledCount = $orders()->where('status', 'cancelled')->count();
        $sales = $this->money($orders()->where('status', '!=', 'cancelled')->sum('grand_total'));
        $liveCount = $orderCount - $cancelledCount;

        return [
            'revenue' => (string) $orders()->where('status', 'delivered')->where('payment_status', 'paid')->sum('grand_total'),
            'orders' => $orderCount,
            'cancelledOrders' => $cancelledCount,
            'sales' => $sales,
            'averageOrderValue' => $liveCount > 0 ? (int) round($sales / $liveCount) : 0,
        ];
    }

    /**
     * Percent change against the previous window; null when there is nothing
     * to compare with (no previous window, or it was zero).
     */
    public function change(float|int|string $current, float|int|string $previous): ?float
    {
        $previous = (float) $previous;

        if ($previous <= 0) {
            return null;
        }

        return round(((float) $current - $previous) / $previous * 100, 1);
    }

    /**
     * Sales (non-cancelled orders) per hour / day / month across the window,
     * every bucket present even when empty so the line has no gaps.
     *
     * @return list<array{label: string, longLabel: string, sales: int, orders: int}>
     */
    public function salesSeries(DashboardPeriod $period): array
    {
        $granularity = $period->granularity();
        $start = $period->start ?? $this->firstOrderMonth() ?? $period->end->startOfMonth();
        [$keyFormat, $step] = match ($granularity) {
            'hour' => ['Y-m-d H', 'addHour'],
            'month' => ['Y-m', 'addMonth'],
            default => ['Y-m-d', 'addDay'],
        };

        $buckets = [];
        $cursor = match ($granularity) {
            'hour' => $start->startOfHour(),
            'month' => $start->startOfMonth(),
            default => $start->startOfDay(),
        };

        while ($cursor <= $period->end) {
            $buckets[$cursor->format($keyFormat)] = [
                'label' => $this->bucketLabel($cursor, $granularity),
                'longLabel' => $this->bucketLongLabel($cursor, $granularity),
                'sales' => 0,
                'orders' => 0,
            ];
            $cursor = $cursor->{$step}();
        }

        $period->apply(Order::query())
            ->where('status', '!=', 'cancelled')
            ->select(['id', 'placed_at', 'grand_total'])
            ->toBase()
            ->lazyById(1000, 'id')
            ->each(function (object $order) use (&$buckets, $keyFormat): void {
                $key = Carbon::parse($order->placed_at)->format($keyFormat);

                if (isset($buckets[$key])) {
                    $buckets[$key]['sales'] += $this->money($order->grand_total);
                    $buckets[$key]['orders']++;
                }
            });

        return array_values($buckets);
    }

    /**
     * Best sellers by units, from the order-line snapshots of non-cancelled
     * orders (so a product renamed or removed since still shows as sold).
     *
     * @return list<array{name: string, quantity: int, sales: int}>
     */
    public function topProducts(DashboardPeriod $period, int $limit = 5): array
    {
        return $period->apply(OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id'), 'orders.placed_at')
            ->where('orders.status', '!=', 'cancelled')
            ->groupBy('order_items.product_name')
            ->selectRaw('order_items.product_name as name, SUM(order_items.quantity) as quantity, SUM(order_items.line_total) as sales')
            ->orderByDesc('quantity')
            ->orderByDesc('sales')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'quantity' => (int) $row->quantity,
                'sales' => $this->money($row->sales),
            ])
            ->all();
    }

    /**
     * Order count per status, pipeline first, then cancelled / returned.
     *
     * @return list<array{status: string, label: string, count: int, inPipeline: bool}>
     */
    public function statusBreakdown(DashboardPeriod $period): array
    {
        $counts = $period->apply(Order::query())
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(Order::STATUS_LABELS)
            ->map(fn (string $label, string $status): array => [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($counts[$status] ?? 0),
                'inPipeline' => in_array($status, self::PIPELINE, true),
            ])
            ->values()
            ->all();
    }

    /**
     * Variants on sale whose stock is at or below their own warning level,
     * emptiest first.
     *
     * @return Collection<int, ProductVariant>
     */
    public function lowStockVariants(int $limit = 8): Collection
    {
        return $this->lowStockQuery()
            ->with('product:id,name')
            ->orderBy('stock_quantity')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * What needs someone's attention right now — not tied to the period.
     *
     * @return array{pendingOrders: int, pendingPaymentReviews: int, outOfStock: int, lowStock: int}
     */
    public function actionCounts(): array
    {
        return [
            'pendingOrders' => Order::query()->where('status', 'pending')->count(),
            'pendingPaymentReviews' => Order::query()->where('payment_status', 'pending_review')->count(),
            'outOfStock' => $this->lowStockQuery()->where('stock_quantity', '<=', 0)->count(),
            'lowStock' => $this->lowStockQuery()->count(),
        ];
    }

    /**
     * @return Collection<int, Order>
     */
    public function latestOrders(int $limit = 6): Collection
    {
        return Order::query()
            ->latest('placed_at')
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'order_number', 'customer_name', 'status', 'payment_status', 'grand_total', 'placed_at']);
    }

    /**
     * @return Builder<ProductVariant>
     */
    private function lowStockQuery(): Builder
    {
        return ProductVariant::query()
            ->where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->whereHas('product', fn (Builder $product) => $product->where('status', 'active'));
    }

    private function firstOrderMonth(): ?CarbonImmutable
    {
        $first = Order::query()->min('placed_at');

        return $first === null ? null : CarbonImmutable::parse($first)->startOfMonth();
    }

    private function bucketLabel(CarbonImmutable $bucket, string $granularity): string
    {
        return match ($granularity) {
            'hour' => $bucket->format('H').'h',
            'month' => $bucket->format('m/Y'),
            default => $bucket->format('d/m'),
        };
    }

    private function bucketLongLabel(CarbonImmutable $bucket, string $granularity): string
    {
        return match ($granularity) {
            'hour' => $bucket->format('H:00').' – '.$bucket->addHour()->format('H:00').', '.$bucket->format('d/m/Y'),
            'month' => 'Tháng '.$bucket->format('m/Y'),
            default => $bucket->format('d/m/Y'),
        };
    }

    private function money(mixed $amount): int
    {
        return (int) round((float) $amount);
    }
}
