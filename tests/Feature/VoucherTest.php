<?php

namespace Tests\Feature;

use App\Actions\CancelOrder;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Discount;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Pricing\InvalidCouponException;
use App\Services\Pricing\OrderQuote;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shipping vouchers and automatic vouchers: one order voucher and one
 * shipping voucher per order, typed or automatic, whichever takes off more.
 */
class VoucherTest extends TestCase
{
    use RefreshDatabase;

    private PriceCalculator $prices;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prices = new PriceCalculator;
        config(['store.shipping_fee' => 30000]);
    }

    /** A quote for one line worth `$subtotal`. */
    private function quote(int $subtotal, ?string $code = null, ?User $customer = null): OrderQuote
    {
        $variant = ProductVariant::factory()->create(['price' => $subtotal]);
        $coupon = $code === null ? null : $this->prices->findCoupon($code);

        return $this->prices->quote(collect([$this->prices->priceVariant($variant)]), $coupon, $customer);
    }

    public function test_a_typed_shipping_voucher_takes_its_share_off_the_shipping_fee(): void
    {
        Discount::factory()->shippingVoucher()->create(['code' => 'SHIP50', 'discount_value' => 50]);

        $quote = $this->quote(200000, 'ship50');

        $this->assertSame(30000, $quote->shippingFee);
        $this->assertSame(15000, $quote->shippingDiscountAmount);
        $this->assertSame(200000 + 30000 - 15000, $quote->grandTotal);
        $this->assertSame('SHIP50', $quote->shippingVoucher->code);
        $this->assertNull($quote->coupon);
    }

    public function test_a_fixed_shipping_voucher_never_takes_off_more_than_the_fee(): void
    {
        Discount::factory()->shippingVoucher()->create(['code' => 'SHIP99K', 'discount_type' => 'fixed', 'discount_value' => 99000]);

        $this->assertSame(30000, $this->quote(200000, 'SHIP99K')->shippingDiscountAmount);
    }

    public function test_automatic_free_shipping_starts_exactly_at_its_minimum_order(): void
    {
        Discount::factory()->shippingVoucher()->automatic()->create(['min_order_amount' => 500000]);

        $below = $this->quote(499000);
        $this->assertSame(0, $below->shippingDiscountAmount);
        $this->assertNull($below->shippingVoucher);
        $this->assertSame(1000, $below->freeShippingGap);

        foreach ([500000, 650000] as $subtotal) {
            $quote = $this->quote($subtotal);
            $this->assertSame(30000, $quote->shippingDiscountAmount);
            $this->assertSame($subtotal, $quote->grandTotal);
            $this->assertNull($quote->freeShippingGap);
        }
    }

    public function test_the_free_shipping_minimum_is_checked_after_the_order_voucher(): void
    {
        Discount::factory()->shippingVoucher()->automatic()->create(['min_order_amount' => 500000]);
        Discount::factory()->coupon()->create(['product_variant_id' => null, 'code' => 'GIAM50K', 'discount_value' => 50000]);

        $quote = $this->quote(520000, 'GIAM50K');

        $this->assertSame(50000, $quote->discountAmount);
        $this->assertSame(0, $quote->shippingDiscountAmount);
        $this->assertSame(30000, $quote->freeShippingGap);
    }

    public function test_an_order_holds_one_order_voucher_and_one_shipping_voucher(): void
    {
        Discount::factory()->shippingVoucher()->automatic()->create();
        Discount::factory()->coupon()->automatic()->create(['discount_value' => 20000]);

        $quote = $this->quote(300000);

        $this->assertSame(20000, $quote->discountAmount);
        $this->assertSame(30000, $quote->shippingDiscountAmount);
        $this->assertSame(300000 - 20000, $quote->grandTotal);
    }

    public function test_the_bigger_of_the_typed_code_and_an_automatic_voucher_wins(): void
    {
        Discount::factory()->coupon()->automatic()->create(['name' => 'Giảm 20.000 ₫ tự động', 'discount_value' => 20000]);
        Discount::factory()->coupon()->create(['product_variant_id' => null, 'code' => 'NHO10K', 'discount_value' => 10000]);
        Discount::factory()->coupon()->create(['product_variant_id' => null, 'code' => 'LON50K', 'discount_value' => 50000]);

        $smaller = $this->quote(300000, 'NHO10K');
        $this->assertSame(20000, $smaller->discountAmount);
        $this->assertTrue($smaller->coupon->is_auto_apply);
        $this->assertStringContainsString('Giảm 20.000 ₫ tự động', $smaller->notice);

        $bigger = $this->quote(300000, 'LON50K');
        $this->assertSame(50000, $bigger->discountAmount);
        $this->assertSame('LON50K', $bigger->coupon->code);
        $this->assertNull($bigger->notice);
    }

    public function test_automatic_vouchers_that_are_off_out_of_dates_or_used_up_do_not_apply(): void
    {
        $voucher = Discount::factory()->shippingVoucher()->automatic();
        $voucher->create(['is_active' => false]);
        $voucher->create(['starts_at' => now()->addDay()]);
        $voucher->create(['starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);
        $voucher->create(['usage_limit' => 2, 'used_count' => 2]);

        $quote = $this->quote(300000);

        $this->assertSame(0, $quote->shippingDiscountAmount);
        $this->assertSame(330000, $quote->grandTotal);
    }

    public function test_an_automatic_voucher_honours_its_per_customer_limit(): void
    {
        $customer = User::factory()->create();
        $voucher = Discount::factory()->shippingVoucher()->automatic()->create(['usage_limit_per_customer' => 1]);
        Order::factory()->for($customer)->create(['shipping_discount_id' => $voucher->id, 'status' => 'delivered']);

        $this->assertSame(0, $this->quote(300000, customer: $customer)->shippingDiscountAmount);
        $this->assertSame(30000, $this->quote(300000, customer: User::factory()->create())->shippingDiscountAmount);
    }

    public function test_a_typed_code_that_fails_its_rules_still_reports_why(): void
    {
        Discount::factory()->shippingVoucher()->create(['code' => 'SHIPMIN', 'min_order_amount' => 900000]);

        $this->expectException(InvalidCouponException::class);
        $this->expectExceptionMessage('Mã giảm giá chỉ áp dụng cho đơn từ 900.000 ₫.');

        $this->quote(300000, 'SHIPMIN');
    }

    public function test_placing_an_order_keeps_both_vouchers_and_cancelling_gives_their_uses_back(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->default()->create();
        $variant = ProductVariant::factory()->create(['price' => 600000, 'stock_quantity' => 5]);
        $cart = Cart::query()->create(['user_id' => $customer->id, 'status' => 'active']);
        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);
        $freeShipping = Discount::factory()->shippingVoucher()->automatic()->create(['min_order_amount' => 500000]);
        $coupon = Discount::factory()->coupon()->create(['product_variant_id' => null, 'code' => 'GIAM50K', 'discount_value' => 50000]);

        $this->actingAs($customer)->get(route('cart.index'))
            ->assertSee('Ưu đãi tự động (Miễn phí vận chuyển)');

        $this->actingAs($customer)->post(route('checkout.store'), [
            'address_id' => $address->id, 'payment_method' => 'cod', 'coupon_code' => 'giam50k',
        ])->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame($coupon->id, $order->discount_id);
        $this->assertSame($freeShipping->id, $order->shipping_discount_id);
        $this->assertSame('50000.00', $order->discount_amount);
        $this->assertSame('30000.00', $order->shipping_discount_amount);
        $this->assertSame('550000.00', $order->grand_total);
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertSame(1, $freeShipping->fresh()->used_count);

        $this->actingAs($customer)->get(route('orders.show', $order))->assertSee('Giảm phí vận chuyển');

        app(CancelOrder::class)->execute($order, $customer, 'Đổi ý');
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertSame(0, $freeShipping->fresh()->used_count);
    }

    public function test_an_order_keeps_its_amounts_when_the_voucher_changes_later(): void
    {
        $voucher = Discount::factory()->shippingVoucher()->automatic()->create();
        $order = Order::factory()->create(['shipping_discount_id' => $voucher->id, 'shipping_fee' => 30000, 'shipping_discount_amount' => 30000]);

        $voucher->update(['discount_value' => 10, 'is_active' => false]);
        $voucher->delete();

        $order->refresh();
        $this->assertNull($order->shipping_discount_id);
        $this->assertSame('30000.00', $order->shipping_discount_amount);
    }
}
