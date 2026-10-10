<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Role;
use Database\Seeders\OrderDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_order_history_without_touching_stock_and_only_once(): void
    {
        Role::factory()->create(['code' => 'customer']);
        $variants = ProductVariant::factory()->count(4)->create(['stock_quantity' => 10]);

        $this->seed(OrderDemoSeeder::class);

        $orders = Order::query()->with('items')->get();
        $this->assertGreaterThan(100, $orders->count());
        $this->assertTrue($orders->every(fn (Order $order): bool => str_starts_with($order->order_number, 'DM')));
        $this->assertTrue($orders->every(fn (Order $order): bool => $order->items->isNotEmpty()
            && (int) round((float) $order->grand_total) === (int) round((float) $order->items->sum('line_total') + (float) $order->shipping_fee)));
        $this->assertTrue($orders->where('status', 'delivered')->every(fn (Order $order): bool => $order->payment_status === 'paid'));
        $this->assertFalse($orders->contains(fn (Order $order): bool => $order->placed_at->isFuture()));
        $this->assertSame([10, 10, 10, 10], $variants->map(fn (ProductVariant $variant): int => $variant->fresh()->stock_quantity)->all());

        $count = $orders->count();
        $this->seed(OrderDemoSeeder::class);
        $this->assertSame($count, Order::query()->count());
    }

    public function test_it_does_nothing_without_products(): void
    {
        $this->seed(OrderDemoSeeder::class);

        $this->assertSame(0, Order::query()->count());
    }
}
