<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\Reports\DashboardPeriod;
use App\Services\Reports\SalesDashboard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-10 15:30:00'));
    }

    private function order(string $placedAt, int $total, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'placed_at' => CarbonImmutable::parse($placedAt),
            'grand_total' => $total,
            'subtotal' => $total,
            'shipping_fee' => 0,
        ], $attributes));
    }

    private function line(Order $order, string $name, int $quantity, int $lineTotal): void
    {
        $order->items()->create([
            'product_name' => $name,
            'sku' => 'SKU-'.$name,
            'size_name' => 'M',
            'color_name' => 'Đen',
            'original_unit_price' => $lineTotal / $quantity,
            'unit_price' => $lineTotal / $quantity,
            'quantity' => $quantity,
            'line_total' => $lineTotal,
        ]);
    }

    public function test_periods_default_to_thirty_days_and_compare_with_the_window_before(): void
    {
        $period = DashboardPeriod::fromKey('khong-hop-le');

        $this->assertSame('30-ngay', $period->key);
        $this->assertSame('2026-09-11 00:00:00', $period->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-12 00:00:00', $period->previous()->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-10 15:30:00', $period->previous()->end->format('Y-m-d H:i:s'));
        $this->assertSame('so với 30 ngày trước', $period->comparisonLabel());

        $today = DashboardPeriod::fromKey('hom-nay');
        $this->assertSame('hour', $today->granularity());
        $this->assertSame('so với hôm qua', $today->comparisonLabel());

        $allTime = DashboardPeriod::fromKey('tat-ca');
        $this->assertNull($allTime->start);
        $this->assertNull($allTime->previous());
        $this->assertSame('month', $allTime->granularity());
    }

    public function test_totals_only_count_the_window_and_compare_with_the_previous_one(): void
    {
        $this->order('2026-10-09 10:00', 300000, ['status' => 'delivered', 'payment_status' => 'paid']);
        $this->order('2026-10-08 10:00', 200000, ['status' => 'pending']);
        $this->order('2026-10-07 10:00', 900000, ['status' => 'cancelled']);
        $this->order('2026-09-01 10:00', 100000, ['status' => 'delivered', 'payment_status' => 'paid']);
        $this->order('2026-06-01 10:00', 999000, ['status' => 'delivered', 'payment_status' => 'paid']);

        $dashboard = new SalesDashboard;
        $period = DashboardPeriod::fromKey('30-ngay');
        $totals = $dashboard->totals($period);
        $previous = $dashboard->totals($period->previous());

        $this->assertEquals(300000, $totals['revenue']);
        $this->assertSame(3, $totals['orders']);
        $this->assertSame(1, $totals['cancelledOrders']);
        $this->assertSame(500000, $totals['sales']);
        $this->assertSame(250000, $totals['averageOrderValue']);

        $this->assertEquals(100000, $previous['revenue']);
        $this->assertSame(200.0, $dashboard->change($totals['revenue'], $previous['revenue']));
        $this->assertSame(-50.0, $dashboard->change(1, 2));
        $this->assertNull($dashboard->change(5, 0));
    }

    public function test_sales_series_has_every_bucket_and_skips_cancelled_orders(): void
    {
        $this->order('2026-10-10 09:15', 120000);
        $this->order('2026-10-10 09:45', 80000);
        $this->order('2026-10-10 11:00', 50000, ['status' => 'cancelled']);
        $this->order('2026-10-09 23:00', 999000);

        $series = (new SalesDashboard)->salesSeries(DashboardPeriod::fromKey('hom-nay'));

        $this->assertCount(16, $series);
        $this->assertSame('09h', $series[9]['label']);
        $this->assertSame(200000, $series[9]['sales']);
        $this->assertSame(2, $series[9]['orders']);
        $this->assertSame(0, $series[11]['sales']);
        $this->assertSame(200000, array_sum(array_column($series, 'sales')));

        $days = (new SalesDashboard)->salesSeries(DashboardPeriod::fromKey('7-ngay'));
        $this->assertCount(7, $days);
        $this->assertSame('04/10', $days[0]['label']);
        $this->assertSame(999000, $days[5]['sales']);
    }

    public function test_all_time_series_runs_monthly_from_the_first_order(): void
    {
        $this->order('2026-07-20 10:00', 100000);
        $this->order('2026-10-01 10:00', 50000);

        $series = (new SalesDashboard)->salesSeries(DashboardPeriod::fromKey('tat-ca'));

        $this->assertSame(['07/2026', '08/2026', '09/2026', '10/2026'], array_column($series, 'label'));
        $this->assertSame([100000, 0, 0, 50000], array_column($series, 'sales'));
    }

    public function test_best_sellers_rank_by_units_and_ignore_cancelled_or_older_orders(): void
    {
        $recent = $this->order('2026-10-05 10:00', 1);
        $this->line($recent, 'Áo thun', 3, 300000);
        $this->line($recent, 'Quần jean', 1, 500000);
        $other = $this->order('2026-10-06 10:00', 1);
        $this->line($other, 'Quần jean', 1, 500000);
        $this->line($other, 'Áo thun', 2, 200000);
        $this->line($this->order('2026-10-06 11:00', 1, ['status' => 'cancelled']), 'Mũ', 50, 50000);
        $this->line($this->order('2026-01-01 10:00', 1), 'Mũ', 70, 70000);

        $top = (new SalesDashboard)->topProducts(DashboardPeriod::fromKey('30-ngay'));

        $this->assertSame([
            ['name' => 'Áo thun', 'quantity' => 5, 'sales' => 500000],
            ['name' => 'Quần jean', 'quantity' => 2, 'sales' => 1000000],
        ], $top);
    }

    public function test_status_breakdown_lists_every_status_in_pipeline_order(): void
    {
        $this->order('2026-10-05 10:00', 1, ['status' => 'shipping']);
        $this->order('2026-10-05 11:00', 1, ['status' => 'shipping']);
        $this->order('2026-10-05 12:00', 1, ['status' => 'cancelled']);

        $rows = (new SalesDashboard)->statusBreakdown(DashboardPeriod::fromKey('30-ngay'));

        $this->assertSame(array_keys(Order::STATUS_LABELS), array_column($rows, 'status'));
        $this->assertSame(2, $rows[3]['count']);
        $this->assertTrue($rows[3]['inPipeline']);
        $this->assertSame(1, $rows[5]['count']);
        $this->assertFalse($rows[5]['inPipeline']);
    }

    public function test_low_stock_covers_active_variants_of_active_products_emptiest_first(): void
    {
        $empty = ProductVariant::factory()->create(['stock_quantity' => 0, 'low_stock_threshold' => 5]);
        $low = ProductVariant::factory()->create(['stock_quantity' => 3, 'low_stock_threshold' => 5]);
        ProductVariant::factory()->create(['stock_quantity' => 30, 'low_stock_threshold' => 5]);
        ProductVariant::factory()->inactive()->create(['stock_quantity' => 0]);
        ProductVariant::factory()->for(Product::factory()->draft())->create(['stock_quantity' => 0]);

        $dashboard = new SalesDashboard;

        $this->assertSame([$empty->id, $low->id], $dashboard->lowStockVariants()->pluck('id')->all());
        $this->assertSame(['pendingOrders' => 0, 'pendingPaymentReviews' => 0, 'outOfStock' => 1, 'lowStock' => 2], $dashboard->actionCounts());
    }

    public function test_dashboard_page_renders_every_section_for_the_chosen_period(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('2026-10-09 10:00', 330000, ['status' => 'pending']);
        $this->line($order, 'Áo khoác gió', 2, 300000);
        ProductVariant::factory()->create(['stock_quantity' => 0, 'low_stock_threshold' => 5]);

        $this->actingAs($admin)->get(route('admin.dashboard', ['khoang' => '7-ngay']))
            ->assertOk()
            ->assertSee('aria-current="page"', false)
            ->assertSee('1</strong> đơn chờ xác nhận', false)
            ->assertSee('biến thể đã hết hàng')
            ->assertSee('Doanh số theo ngày')
            ->assertSee('Áo khoác gió')
            ->assertSee('Hết hàng')
            ->assertSee($order->order_number)
            ->assertSee(route('admin.orders.index', ['status' => 'pending']), false)
            ->assertViewHas('salesSeries', fn (array $series): bool => count($series) === 7);
    }

    public function test_staff_without_order_rights_see_numbers_but_no_order_links(): void
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->where('code', 'admin.access')->pluck('id'));
        $viewer = User::factory()->create(['role_id' => $role->id]);
        $order = $this->order('2026-10-09 10:00', 330000);

        $this->actingAs($viewer)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertDontSee(route('admin.orders.show', $order), false)
            ->assertDontSee(route('admin.orders.index'), false);
    }
}
