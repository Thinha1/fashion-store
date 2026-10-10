<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\Discount;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Address $address;

    protected function setUp(): void
    {
        parent::setUp();

        config(['store.shipping_fee' => 30000]);
        $this->customer = User::factory()->create();
        $this->address = Address::factory()->for($this->customer)->default()->create();
    }

    private function putInCart(ProductVariant $variant, int $quantity, ?User $user = null): Cart
    {
        $user ??= $this->customer;
        $cart = $user->activeCart()->first() ?? Cart::query()->create(['user_id' => $user->id, 'status' => 'active']);
        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => $quantity]);

        return $cart;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(['address_id' => $this->address->id, 'payment_method' => 'cod'], $overrides);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('checkout.create'))->assertRedirect(route('login'));
        $this->post(route('checkout.store'), [])->assertRedirect(route('login'));
    }

    public function test_empty_cart_cannot_check_out(): void
    {
        $this->actingAs($this->customer)->get(route('checkout.create'))
            ->assertRedirect(route('cart.index'));
    }

    public function test_checkout_page_shows_totals_and_saved_addresses(): void
    {
        $this->putInCart(ProductVariant::factory()->create(['price' => 150000, 'stock_quantity' => 5]), 2);

        $this->actingAs($this->customer)->get(route('checkout.create'))
            ->assertOk()
            ->assertSee($this->address->fullAddress())
            ->assertViewHas('quote', fn ($quote): bool => $quote->subtotal === 300000 && $quote->grandTotal === 330000);
    }

    public function test_checkout_page_previews_a_coupon_and_reports_a_bad_one(): void
    {
        $this->putInCart(ProductVariant::factory()->create(['price' => 300000, 'stock_quantity' => 5]), 1);
        Discount::factory()->coupon()->create(['product_variant_id' => null, 'code' => 'GIAM50']);

        $this->actingAs($this->customer)->get(route('checkout.create', ['ma-giam-gia' => 'giam50']))
            ->assertViewHas('quote', fn ($quote): bool => $quote->discountAmount === 50000 && $quote->grandTotal === 280000);

        $this->actingAs($this->customer)->get(route('checkout.create', ['ma-giam-gia' => 'SAI']))
            ->assertOk()
            ->assertSee('Mã giảm giá không tồn tại.')
            ->assertViewHas('quote', fn ($quote): bool => $quote->discountAmount === 0);
    }

    public function test_placing_a_cod_order_snapshots_lines_takes_stock_and_converts_the_cart(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 200000, 'stock_quantity' => 10]);
        Discount::factory()->for($variant)->create(['discount_type' => 'percent', 'discount_value' => 10]);
        $cart = $this->putInCart($variant, 3);

        $response = $this->actingAs($this->customer)->post(route('checkout.store'), $this->payload([
            'customer_note' => 'Giao giờ hành chính',
        ]));

        $order = Order::query()->sole();
        $response->assertRedirect(route('orders.show', $order))->assertSessionHas('status');

        $this->assertMatchesRegularExpression('/^DH\d{6}[A-Z0-9]{6}$/', $order->order_number);
        $this->assertSame('pending', $order->status);
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame($this->address->recipient_name, $order->customer_name);
        $this->assertSame($this->customer->email, $order->customer_email);
        $this->assertSame('Giao giờ hành chính', $order->customer_note);
        $this->assertEquals(540000, $order->subtotal);
        $this->assertEquals(30000, $order->shipping_fee);
        $this->assertEquals(570000, $order->grand_total);
        $this->assertSame('pending', $order->status_history[0]['to']);

        $item = $order->items()->sole();
        $this->assertSame($variant->sku, $item->sku);
        $this->assertEquals(200000, $item->original_unit_price);
        $this->assertEquals(180000, $item->unit_price);
        $this->assertEquals(60000, $item->discount_amount);
        $this->assertEquals(540000, $item->line_total);

        $this->assertSame(7, $variant->fresh()->stock_quantity);
        $this->assertSame('converted', $cart->fresh()->status);
        $this->assertNull($this->customer->activeCart()->first());

        $log = AuditLog::query()->where('action', 'order.placed')->sole();
        $this->assertSame(10, $log->new_values['stock'][0]['stock_before']);
        $this->assertSame(7, $log->new_values['stock'][0]['stock_after']);
    }

    public function test_totals_come_from_the_server_not_the_form(): void
    {
        $this->putInCart(ProductVariant::factory()->create(['price' => 200000, 'stock_quantity' => 5]), 1);

        $this->actingAs($this->customer)->post(route('checkout.store'), $this->payload([
            'grand_total' => 1000,
            'subtotal' => 1000,
            'shipping_fee' => 0,
        ]));

        $this->assertEquals(230000, Order::query()->sole()->grand_total);
    }

    public function test_coupon_is_applied_and_its_use_counted(): void
    {
        $this->putInCart(ProductVariant::factory()->create(['price' => 400000, 'stock_quantity' => 5]), 1);
        $coupon = Discount::factory()->coupon()->create([
            'product_variant_id' => null,
            'code' => 'GIAM50',
            'usage_limit' => 10,
            'used_count' => 2,
        ]);

        $this->actingAs($this->customer)->post(route('checkout.store'), $this->payload(['coupon_code' => 'GIAM50']));

        $order = Order::query()->sole();
        $this->assertTrue($order->discount->is($coupon));
        $this->assertEquals(50000, $order->discount_amount);
        $this->assertEquals(380000, $order->grand_total);
        $this->assertSame(3, $coupon->fresh()->used_count);
    }

    public function test_invalid_coupon_rolls_everything_back(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 400000, 'stock_quantity' => 5]);
        $cart = $this->putInCart($variant, 1);
        Discount::factory()->coupon()->create([
            'product_variant_id' => null,
            'code' => 'HETLUOT',
            'usage_limit' => 1,
            'used_count' => 1,
        ]);

        $this->actingAs($this->customer)->post(route('checkout.store'), $this->payload(['coupon_code' => 'HETLUOT']))
            ->assertRedirect(route('checkout.create'))
            ->assertSessionHasErrors(['coupon_code' => 'Mã giảm giá đã hết lượt sử dụng.']);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(5, $variant->fresh()->stock_quantity);
        $this->assertSame('active', $cart->fresh()->status);
    }

    public function test_stock_bought_by_someone_else_first_blocks_the_order(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 100000, 'stock_quantity' => 2]);
        $cart = $this->putInCart($variant, 2);

        $rival = User::factory()->create();
        Address::factory()->for($rival)->default()->create();
        $this->putInCart($variant, 2, $rival);
        $this->actingAs($rival)->post(route('checkout.store'), ['address_id' => $rival->addresses()->first()->id, 'payment_method' => 'cod']);
        $this->assertSame(0, $variant->fresh()->stock_quantity);

        $this->actingAs($this->customer)->post(route('checkout.store'), $this->payload())
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'đã hết hàng'));

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, $variant->fresh()->stock_quantity);
        $this->assertSame('active', $cart->fresh()->status);
    }

    public function test_new_address_typed_at_checkout_is_used_and_saved(): void
    {
        $this->putInCart(ProductVariant::factory()->create(['stock_quantity' => 5]), 1);

        $this->actingAs($this->customer)->post(route('checkout.store'), $this->payload([
            'address_id' => 'new',
            'recipient_name' => 'Trần Thị B',
            'phone' => '0912345678',
            'province_name' => 'Hà Nội',
            'district_name' => 'Quận Ba Đình',
            'ward_name' => 'Phường Kim Mã',
            'address_line' => '1 Kim Mã',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Trần Thị B', Order::query()->sole()->customer_name);
        $this->assertSame(2, $this->customer->addresses()->count());
    }

    public function test_someone_elses_address_cannot_be_used(): void
    {
        $this->putInCart(ProductVariant::factory()->create(['stock_quantity' => 5]), 1);
        $foreign = Address::factory()->create();

        $this->actingAs($this->customer)->post(route('checkout.store'), $this->payload(['address_id' => $foreign->id]))
            ->assertSessionHasErrors('address_id');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_only_cod_is_accepted_for_now(): void
    {
        $this->putInCart(ProductVariant::factory()->create(['stock_quantity' => 5]), 1);

        $this->actingAs($this->customer)->post(route('checkout.store'), $this->payload(['payment_method' => 'bank_transfer']))
            ->assertSessionHasErrors('payment_method');
    }

    public function test_customer_sees_their_order_but_not_someone_elses(): void
    {
        $order = Order::factory()->for($this->customer)->create();
        $other = Order::factory()->create();

        $this->actingAs($this->customer)->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Chờ xác nhận');

        $this->actingAs($this->customer)->get(route('orders.show', $other))->assertForbidden();
    }
}
