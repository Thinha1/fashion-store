<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Pricing\InvalidCouponException;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private PriceCalculator $prices;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prices = new PriceCalculator;
        config(['store.shipping_fee' => 30000]);
    }

    public function test_variant_without_price_falls_back_to_product_base_price(): void
    {
        $product = Product::factory()->create(['base_price' => 250000]);
        $variant = ProductVariant::factory()->for($product)->create(['price' => null]);

        $line = $this->prices->priceVariant($variant, 2);

        $this->assertSame(250000, $line->originalUnitPrice);
        $this->assertSame(250000, $line->unitPrice);
        $this->assertSame(500000, $line->lineTotal());
        $this->assertFalse($line->isDiscounted());
    }

    public function test_percent_variant_discount_lowers_unit_price(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 400000]);
        Discount::factory()->for($variant)->create(['discount_type' => 'percent', 'discount_value' => 25]);

        $line = $this->prices->priceVariant($variant, 3);

        $this->assertSame(100000, $line->unitDiscountAmount);
        $this->assertSame(300000, $line->unitPrice);
        $this->assertSame(300000, $line->lineDiscountAmount());
        $this->assertSame(25, $line->discountPercent());
    }

    public function test_variant_discount_is_capped_by_max_discount_amount(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 400000]);
        Discount::factory()->for($variant)->create([
            'discount_type' => 'percent',
            'discount_value' => 50,
            'max_discount_amount' => 80000,
        ]);

        $this->assertSame(320000, $this->prices->priceVariant($variant)->unitPrice);
    }

    public function test_fixed_variant_discount_never_goes_below_zero(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 100000]);
        Discount::factory()->for($variant)->create(['discount_type' => 'fixed', 'discount_value' => 150000]);

        $this->assertSame(0, $this->prices->priceVariant($variant)->unitPrice);
    }

    public function test_inactive_expired_and_future_variant_discounts_are_ignored(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 200000]);
        Discount::factory()->for($variant)->create(['is_active' => false]);
        Discount::factory()->for($variant)->create(['starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);
        Discount::factory()->for($variant)->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addMonth()]);

        $this->assertSame(200000, $this->prices->priceVariant($variant)->unitPrice);
    }

    public function test_best_of_several_active_variant_discounts_wins(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 200000]);
        Discount::factory()->for($variant)->create(['discount_type' => 'percent', 'discount_value' => 10]);
        $best = Discount::factory()->for($variant)->create(['discount_type' => 'fixed', 'discount_value' => 50000]);

        $line = $this->prices->priceVariant($variant);

        $this->assertSame(150000, $line->unitPrice);
        $this->assertTrue($line->discount->is($best));
    }

    public function test_quote_adds_fixed_shipping_fee(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 100000]);

        $quote = $this->prices->quote(collect([$this->prices->priceVariant($variant, 2)]));

        $this->assertSame(200000, $quote->subtotal);
        $this->assertSame(0, $quote->discountAmount);
        $this->assertSame(30000, $quote->shippingFee);
        $this->assertSame(230000, $quote->grandTotal);
    }

    public function test_valid_coupon_lowers_grand_total_within_its_cap(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500000]);
        $coupon = Discount::factory()->coupon()->create([
            'product_variant_id' => null,
            'discount_type' => 'percent',
            'discount_value' => 20,
            'max_discount_amount' => 150000,
            'min_order_amount' => 300000,
        ]);

        $quote = $this->prices->quote(collect([$this->prices->priceVariant($variant, 2)]), $coupon, User::factory()->create());

        $this->assertSame(1000000, $quote->subtotal);
        $this->assertSame(150000, $quote->discountAmount);
        $this->assertSame(880000, $quote->grandTotal);
    }

    public function test_coupon_lookup_ignores_case_and_surrounding_spaces(): void
    {
        $coupon = Discount::factory()->coupon()->create(['product_variant_id' => null, 'code' => 'SALE10']);

        $this->assertTrue($this->prices->findCoupon('  sale10 ')->is($coupon));
    }

    public function test_unknown_coupon_code_is_rejected(): void
    {
        $this->expectException(InvalidCouponException::class);

        $this->prices->findCoupon('KHONGCO');
    }

    public function test_expired_coupon_is_rejected(): void
    {
        $coupon = Discount::factory()->coupon()->create([
            'product_variant_id' => null,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);

        $this->expectExceptionObject(new InvalidCouponException('Mã giảm giá đã hết hạn.'));

        $this->prices->couponDiscount($coupon, 500000, User::factory()->create());
    }

    public function test_coupon_over_its_usage_limit_is_rejected(): void
    {
        $coupon = Discount::factory()->coupon()->create([
            'product_variant_id' => null,
            'usage_limit' => 5,
            'used_count' => 5,
        ]);

        $this->expectExceptionObject(new InvalidCouponException('Mã giảm giá đã hết lượt sử dụng.'));

        $this->prices->couponDiscount($coupon, 500000, User::factory()->create());
    }

    public function test_coupon_below_minimum_order_amount_is_rejected(): void
    {
        $coupon = Discount::factory()->coupon()->create([
            'product_variant_id' => null,
            'min_order_amount' => 600000,
        ]);

        $this->expectException(InvalidCouponException::class);

        $this->prices->couponDiscount($coupon, 500000, User::factory()->create());
    }

    public function test_per_customer_limit_counts_only_that_customers_live_orders(): void
    {
        $coupon = Discount::factory()->coupon()->create([
            'product_variant_id' => null,
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'usage_limit_per_customer' => 1,
        ]);
        $customer = User::factory()->create();
        Order::factory()->for($customer)->create(['discount_id' => $coupon->id, 'status' => 'cancelled']);
        Order::factory()->create(['discount_id' => $coupon->id]);

        $this->assertSame(50000, $this->prices->couponDiscount($coupon, 500000, $customer));

        Order::factory()->for($customer)->create(['discount_id' => $coupon->id]);

        $this->expectException(InvalidCouponException::class);
        $this->prices->couponDiscount($coupon, 500000, $customer);
    }

    public function test_product_page_shows_discounted_variant_price(): void
    {
        $product = Product::factory()->create(['base_price' => 300000]);
        $variant = ProductVariant::factory()->for($product)->create(['price' => 300000, 'stock_quantity' => 5]);
        Discount::factory()->for($variant)->create(['discount_type' => 'percent', 'discount_value' => 20]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertViewHas('variantPrices', fn ($prices): bool => $prices[$variant->id]->unitPrice === 240000
                && $prices[$variant->id]->originalUnitPrice === 300000
                && $prices[$variant->id]->discountPercent() === 20);
    }
}
