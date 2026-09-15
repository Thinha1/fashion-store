<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_zero_totals_when_the_store_is_empty(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertViewHas('totalRevenue', '0')
            ->assertViewHas('totalOrders', 0)
            ->assertViewHas('totalProducts', 0)
            ->assertSee('Tổng doanh thu')
            ->assertSee('Tổng đơn hàng')
            ->assertSee('Tổng sản phẩm');
    }

    public function test_dashboard_totals_count_orders_and_products_and_only_recognize_paid_delivered_revenue(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        ProductVariant::factory()->count(3)->create(['product_id' => $product->id]);
        Product::factory()->draft()->create();
        Product::factory()->create()->delete();

        foreach ([
            ['delivered', 'paid', 'cod', '430000.25'],
            ['delivered', 'paid', 'bank_transfer', '270000.00'],
            ['pending', 'paid', 'bank_transfer', '990000.00'],
            ['shipping', 'paid', 'bank_transfer', '880000.00'],
            ['cancelled', 'paid', 'bank_transfer', '770000.00'],
            ['returned', 'paid', 'cod', '660000.00'],
            ['delivered', 'unpaid', 'cod', '550000.00'],
            ['delivered', 'refunded', 'bank_transfer', '440000.00'],
        ] as $index => [$status, $paymentStatus, $paymentMethod, $grandTotal]) {
            Order::query()->create([
                'order_number' => 'DASHBOARD-'.$index,
                'user_id' => $admin->id,
                'status' => $status,
                'status_history' => [],
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'customer_name' => 'Khách kiểm thử',
                'customer_email' => 'dashboard@example.com',
                'customer_phone' => '0900000000',
                'province_name' => 'TP. Hồ Chí Minh',
                'district_name' => 'Quận 1',
                'ward_name' => 'Bến Nghé',
                'shipping_address' => '1 Nguyễn Huệ',
                'subtotal' => $grandTotal,
                'discount_amount' => 30000,
                'shipping_fee' => 30000,
                'grand_total' => $grandTotal,
                'placed_at' => now()->subYears($index + 1),
            ]);
        }

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('totalRevenue', fn ($value) => (float) $value === 700000.25)
            ->assertViewHas('totalOrders', 8)
            ->assertViewHas('totalProducts', 2)
            ->assertSee('700.000')
            ->assertSee('Toàn thời gian');
    }
}
