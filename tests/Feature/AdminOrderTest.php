<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Permission;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function staffWith(string ...$codes): User
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->whereIn('code', $codes)->pluck('id'));

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function orderWithItem(ProductVariant $variant, int $quantity, array $attributes = []): Order
    {
        $order = Order::factory()->create($attributes);
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

    public function test_order_screens_need_the_orders_permission(): void
    {
        $order = Order::factory()->create();
        $catalogStaff = $this->staffWith('admin.access', 'products.manage');

        $this->actingAs($catalogStaff)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($catalogStaff)->get(route('admin.orders.show', $order))->assertForbidden();
        $this->actingAs($catalogStaff)->patch(route('admin.orders.status', $order), ['status' => 'confirmed'])->assertForbidden();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_staff_with_orders_permission_can_work_orders(): void
    {
        $order = Order::factory()->create();
        $orderStaff = $this->staffWith('admin.access', 'orders.manage');

        $this->actingAs($orderStaff)->get(route('admin.orders.index'))->assertOk()->assertSee($order->order_number);
        $this->actingAs($orderStaff)->patch(route('admin.orders.status', $order), ['status' => 'confirmed']);

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_index_filters_by_status_and_searches(): void
    {
        $pending = Order::factory()->create(['customer_name' => 'Nguyễn An', 'customer_phone' => '0901111111']);
        $shipping = Order::factory()->create(['status' => 'shipping', 'customer_name' => 'Trần Bình']);

        $this->actingAs($this->admin)->get(route('admin.orders.index', ['status' => 'shipping']))
            ->assertOk()
            ->assertSee($shipping->order_number)
            ->assertDontSee($pending->order_number);

        $this->actingAs($this->admin)->get(route('admin.orders.index', ['q' => '0901111111']))
            ->assertSee($pending->order_number)
            ->assertDontSee($shipping->order_number);

        $this->actingAs($this->admin)->get(route('admin.orders.index', ['q' => $shipping->order_number]))
            ->assertSee($shipping->order_number)
            ->assertDontSee($pending->order_number);
    }

    public function test_order_moves_one_step_at_a_time_to_delivered(): void
    {
        $order = Order::factory()->create();

        foreach (['confirmed', 'preparing', 'shipping', 'delivered'] as $next) {
            $this->actingAs($this->admin)->patch(route('admin.orders.status', $order), ['status' => $next])
                ->assertSessionHas('status');
            $this->assertSame($next, $order->fresh()->status);
        }

        $order->refresh();
        $this->assertSame(
            ['pending', 'confirmed', 'preparing', 'shipping'],
            collect($order->status_history)->pluck('from')->all(),
        );
        $this->assertSame($this->admin->id, collect($order->status_history)->last()['actor_id']);
        $this->assertSame($this->admin->id, $order->updated_by);
        $this->assertSame(4, AuditLog::query()->where('action', 'order.status_changed')->count());
    }

    public function test_steps_cannot_be_skipped_or_reversed(): void
    {
        $pending = Order::factory()->create();
        $delivered = Order::factory()->create(['status' => 'delivered']);

        $this->actingAs($this->admin)->patch(route('admin.orders.status', $pending), ['status' => 'shipping'])
            ->assertSessionHas('error', 'Không thể chuyển đơn từ "Chờ xác nhận" sang "Đang giao hàng".');
        $this->actingAs($this->admin)->patch(route('admin.orders.status', $delivered), ['status' => 'confirmed'])
            ->assertSessionHas('error');

        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame('delivered', $delivered->fresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_unknown_target_status_is_a_validation_error(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin)->patch(route('admin.orders.status', $order), ['status' => 'returned'])
            ->assertSessionHasErrors('status');
    }

    public function test_delivering_a_cod_order_marks_it_paid_for_revenue(): void
    {
        $order = Order::factory()->create(['status' => 'shipping', 'payment_method' => 'cod', 'grand_total' => 330000]);

        $this->actingAs($this->admin)->patch(route('admin.orders.status', $order), ['status' => 'delivered']);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertViewHas('totalRevenue', fn ($revenue): bool => (float) $revenue === 330000.0);
    }

    public function test_staff_cancel_of_a_confirmed_order_needs_a_reason_and_restocks(): void
    {
        $variant = ProductVariant::factory()->create(['stock_quantity' => 5]);
        $coupon = Discount::factory()->coupon()->create(['product_variant_id' => null, 'used_count' => 1]);
        $order = $this->orderWithItem($variant, 2, ['status' => 'confirmed', 'discount_id' => $coupon->id]);

        $this->actingAs($this->admin)->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])
            ->assertSessionHasErrors('note');
        $this->assertSame('confirmed', $order->fresh()->status);

        $this->actingAs($this->admin)->patch(route('admin.orders.status', $order), [
            'status' => 'cancelled',
            'note' => 'Khách gọi điện hủy',
        ])->assertSessionHas('status');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('Khách gọi điện hủy', collect($order->status_history)->last()['note']);
        $this->assertSame($this->admin->id, $order->updated_by);
        $this->assertSame(7, $variant->fresh()->stock_quantity);
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_orders_already_being_prepared_cannot_be_cancelled(): void
    {
        $variant = ProductVariant::factory()->create(['stock_quantity' => 5]);
        $order = $this->orderWithItem($variant, 2, ['status' => 'preparing']);

        $this->actingAs($this->admin)->patch(route('admin.orders.status', $order), ['status' => 'cancelled', 'note' => 'Thử'])
            ->assertSessionHas('error');

        $this->assertSame('preparing', $order->fresh()->status);
        $this->assertSame(5, $variant->fresh()->stock_quantity);
    }

    public function test_show_page_lists_items_history_and_only_valid_actions(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = $this->orderWithItem($variant, 1, ['status' => 'confirmed', 'status_history' => [
            ['from' => null, 'to' => 'pending', 'actor_id' => null, 'note' => 'Khách đặt hàng', 'at' => now()->toIso8601String()],
            ['from' => 'pending', 'to' => 'confirmed', 'actor_id' => $this->admin->id, 'note' => null, 'at' => now()->toIso8601String()],
        ]]);

        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee($variant->sku)
            ->assertSee('Chuẩn bị hàng')
            ->assertSee('Hủy đơn')
            ->assertDontSee('Giao cho vận chuyển')
            ->assertSee($this->admin->name)
            ->assertSee('Khách đặt hàng');
    }

    public function test_sidebar_shows_pending_order_count(): void
    {
        Order::factory()->count(2)->create();
        Order::factory()->create(['status' => 'confirmed']);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertSee('title="Đơn chờ xác nhận">2</span>', false);
    }
}
