<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Discount;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithItem(User $customer, ProductVariant $variant, int $quantity, array $attributes = []): Order
    {
        $order = Order::factory()->for($customer)->create($attributes);
        $order->items()->create([
            'product_variant_id' => $variant->id,
            'product_name' => $variant->product->name,
            'sku' => $variant->sku,
            'size_name' => $variant->size,
            'color_name' => $variant->color,
            'original_unit_price' => 100000,
            'unit_price' => 100000,
            'quantity' => $quantity,
            'line_total' => 100000 * $quantity,
        ]);

        return $order;
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('orders.index'))->assertRedirect(route('login'));
    }

    public function test_order_list_shows_only_the_customers_orders_newest_first(): void
    {
        $customer = User::factory()->create();
        $older = Order::factory()->for($customer)->create(['placed_at' => now()->subDays(2)]);
        $newer = Order::factory()->for($customer)->create(['placed_at' => now()]);
        $foreign = Order::factory()->create();

        $this->actingAs($customer)->get(route('orders.index'))
            ->assertOk()
            ->assertSeeInOrder([$newer->order_number, $older->order_number])
            ->assertDontSee($foreign->order_number);
    }

    public function test_cancelling_a_pending_order_restocks_and_records_history(): void
    {
        $customer = User::factory()->create();
        $variant = ProductVariant::factory()->create(['stock_quantity' => 4]);
        $order = $this->orderWithItem($customer, $variant, 3);

        $this->actingAs($customer)->patch(route('orders.cancel', $order))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('status', 'Đã hủy đơn hàng.');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame(7, $variant->fresh()->stock_quantity);
        $lastChange = collect($order->status_history)->last();
        $this->assertSame('pending', $lastChange['from']);
        $this->assertSame('cancelled', $lastChange['to']);
        $this->assertSame($customer->id, $lastChange['actor_id']);

        $log = AuditLog::query()->where('action', 'order.cancelled')->sole();
        $this->assertSame(4, $log->new_values['stock'][0]['stock_before']);
        $this->assertSame(7, $log->new_values['stock'][0]['stock_after']);
    }

    public function test_cancelling_gives_the_coupon_use_back(): void
    {
        $customer = User::factory()->create();
        $coupon = Discount::factory()->coupon()->create(['product_variant_id' => null, 'used_count' => 3]);
        $order = $this->orderWithItem($customer, ProductVariant::factory()->create(), 1, ['discount_id' => $coupon->id]);

        $this->actingAs($customer)->patch(route('orders.cancel', $order));

        $this->assertSame(2, $coupon->fresh()->used_count);
    }

    public function test_confirmed_order_cannot_be_cancelled_by_the_customer(): void
    {
        $customer = User::factory()->create();
        $variant = ProductVariant::factory()->create(['stock_quantity' => 4]);
        $order = $this->orderWithItem($customer, $variant, 2, ['status' => 'confirmed']);

        $this->actingAs($customer)->patch(route('orders.cancel', $order))
            ->assertSessionHas('error', 'Đơn hàng đang ở trạng thái "Đã xác nhận" nên không thể hủy.');

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(4, $variant->fresh()->stock_quantity);
    }

    public function test_cancelling_twice_does_not_restock_twice(): void
    {
        $customer = User::factory()->create();
        $variant = ProductVariant::factory()->create(['stock_quantity' => 4]);
        $order = $this->orderWithItem($customer, $variant, 2);

        $this->actingAs($customer)->patch(route('orders.cancel', $order));
        $this->actingAs($customer)->patch(route('orders.cancel', $order))->assertSessionHas('error');

        $this->assertSame(6, $variant->fresh()->stock_quantity);
    }

    public function test_customer_cannot_cancel_someone_elses_order(): void
    {
        $variant = ProductVariant::factory()->create(['stock_quantity' => 4]);
        $order = $this->orderWithItem(User::factory()->create(), $variant, 2);

        $this->actingAs(User::factory()->create())->patch(route('orders.cancel', $order))->assertForbidden();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_cancel_button_only_shows_while_pending(): void
    {
        $customer = User::factory()->create();
        $pending = Order::factory()->for($customer)->create();
        $shipping = Order::factory()->for($customer)->create(['status' => 'shipping']);

        $this->actingAs($customer)->get(route('orders.show', $pending))->assertSee('Hủy đơn hàng');
        $this->actingAs($customer)->get(route('orders.show', $shipping))->assertDontSee('Hủy đơn hàng');
    }
}
