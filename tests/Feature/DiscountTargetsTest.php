<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Pricing\InvalidCouponException;
use App\Services\Pricing\OrderQuote;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Brand / product / variant picks on the discount form: variant discounts
 * created many at a time, and order vouchers limited to some of the cart.
 */
class DiscountTargetsTest extends TestCase
{
    use RefreshDatabase;

    private PriceCalculator $prices;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prices = new PriceCalculator;
        config(['store.shipping_fee' => 30000]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** A variant of a product of `$brand`, priced `$price`. */
    private function variant(Brand $brand, int $price = 100000, array $product = [], array $variant = []): ProductVariant
    {
        return ProductVariant::factory()
            ->for(Product::factory()->for($brand)->create($product), 'product')
            ->create(['price' => $price] + $variant);
    }

    /** @param Collection<int, ProductVariant>|array<int, ProductVariant> $variants */
    private function quote(iterable $variants, ?string $code = null): OrderQuote
    {
        $lines = collect($variants)->map(fn (ProductVariant $variant) => $this->prices->priceVariant($variant));

        return $this->prices->quote($lines, $code === null ? null : $this->prices->findCoupon($code));
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'scope' => 'variant',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'starts_at' => now()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'is_active' => 1,
        ];
    }

    // --- an order voucher limited to some of the cart ---

    public function test_a_brand_voucher_takes_off_only_that_brands_lines(): void
    {
        $coolmate = Brand::factory()->create(['name' => 'Coolmate']);
        $levents = Brand::factory()->create(['name' => 'Levents']);
        Discount::factory()->coupon()->create(['code' => 'COOL10', 'discount_type' => 'percent', 'discount_value' => 10, 'targets' => ['brand' => [$coolmate->id]]]);

        $quote = $this->quote([$this->variant($coolmate, 300000), $this->variant($levents, 700000)], 'cool10');

        $this->assertSame(1000000, $quote->subtotal);
        $this->assertSame(30000, $quote->discountAmount, '10% of the 300.000 ₫ of Coolmate, not of the whole 1.000.000 ₫');
        $this->assertSame(1000000 - 30000 + 30000, $quote->grandTotal);
    }

    public function test_products_and_variants_can_be_picked_too(): void
    {
        $brand = Brand::factory()->create();
        $picked = $this->variant($brand, 200000, variant: ['size' => 'M', 'color' => 'Đen']);
        $sameProduct = ProductVariant::factory()->for($picked->product)->create(['price' => 150000, 'size' => 'L', 'color' => 'Đen']);
        $single = $this->variant($brand, 400000);
        $other = $this->variant($brand, 900000);
        Discount::factory()->coupon()->create(['code' => 'PICK', 'discount_type' => 'percent', 'discount_value' => 10, 'targets' => ['product' => [$picked->product_id], 'variant' => [$single->id]]]);

        $quote = $this->quote([$picked, $sameProduct, $single, $other], 'PICK');

        $this->assertSame(75000, $quote->discountAmount, '10% of 200.000 + 150.000 + 400.000');
    }

    public function test_the_minimum_order_counts_only_the_lines_it_applies_to(): void
    {
        $coolmate = Brand::factory()->create();
        $levents = Brand::factory()->create();
        Discount::factory()->coupon()->create(['code' => 'COOL', 'min_order_amount' => 500000, 'targets' => ['brand' => [$coolmate->id]]]);
        $cart = [$this->variant($coolmate, 300000), $this->variant($levents, 900000)];

        try {
            $this->quote($cart, 'COOL');
            $this->fail('1.200.000 ₫ in the cart, but only 300.000 ₫ of it is Coolmate');
        } catch (InvalidCouponException $exception) {
            $this->assertStringContainsString('sản phẩm áp dụng có tổng từ 500.000 ₫', $exception->getMessage());
        }

        $cart[] = $this->variant($coolmate, 250000);
        $this->assertSame(50000, $this->quote($cart, 'COOL')->discountAmount);
    }

    public function test_a_fixed_voucher_never_takes_off_more_than_the_lines_it_applies_to(): void
    {
        $coolmate = Brand::factory()->create();
        $levents = Brand::factory()->create();
        Discount::factory()->coupon()->create(['code' => 'BIG', 'discount_type' => 'fixed', 'discount_value' => 200000, 'targets' => ['brand' => [$coolmate->id]]]);

        $quote = $this->quote([$this->variant($coolmate, 120000), $this->variant($levents, 900000)], 'BIG');

        $this->assertSame(120000, $quote->discountAmount);
    }

    public function test_a_typed_voucher_is_refused_when_nothing_in_the_cart_qualifies(): void
    {
        $coolmate = Brand::factory()->create(['name' => 'Coolmate']);
        Discount::factory()->coupon()->create(['code' => 'COOL', 'targets' => ['brand' => [$coolmate->id]]]);

        $this->expectException(InvalidCouponException::class);
        $this->expectExceptionMessage('chỉ áp dụng cho thương hiệu Coolmate; giỏ hàng chưa có sản phẩm phù hợp');

        $this->quote([$this->variant(Brand::factory()->create(), 500000)], 'COOL');
    }

    public function test_an_automatic_limited_voucher_is_skipped_when_nothing_qualifies_and_used_when_it_does(): void
    {
        $coolmate = Brand::factory()->create();
        Discount::factory()->coupon()->automatic()->create(['discount_type' => 'percent', 'discount_value' => 10, 'targets' => ['brand' => [$coolmate->id]]]);

        $none = $this->quote([$this->variant(Brand::factory()->create(), 500000)]);
        $this->assertNull($none->coupon);
        $this->assertSame(0, $none->discountAmount);

        $some = $this->quote([$this->variant($coolmate, 500000), $this->variant(Brand::factory()->create(), 500000)]);
        $this->assertSame(50000, $some->discountAmount);
    }

    public function test_a_voucher_with_empty_targets_counts_the_whole_order(): void
    {
        Discount::factory()->coupon()->create(['code' => 'ALL', 'discount_type' => 'percent', 'discount_value' => 10, 'targets' => ['brand' => [], 'product' => [], 'variant' => []]]);

        $quote = $this->quote([$this->variant(Brand::factory()->create(), 400000), $this->variant(Brand::factory()->create(), 600000)], 'ALL');

        $this->assertSame(100000, $quote->discountAmount);
    }

    public function test_what_a_voucher_is_limited_to_reads_well(): void
    {
        $brands = collect(['Levents', 'Coolmate', 'Routine'])->map(fn (string $name) => Brand::factory()->create(['name' => $name]));
        $discount = Discount::factory()->coupon()->make(['targets' => ['brand' => $brands->pluck('id')->all(), 'product' => [5, 6], 'variant' => [9]]]);

        $this->assertSame('thương hiệu Coolmate, Levents và 1 thương hiệu khác; 2 sản phẩm; 1 biến thể', $discount->targetSummary());
        $this->assertNull(Discount::factory()->coupon()->make()->targetSummary());
    }

    // --- the form ---

    public function test_picking_a_brand_creates_a_discount_for_each_variant_on_sale(): void
    {
        $coolmate = Brand::factory()->create();
        $onSale = collect([$this->variant($coolmate), $this->variant($coolmate)]);
        $hidden = $this->variant($coolmate, variant: ['is_active' => false]);
        $draft = $this->variant($coolmate, product: ['status' => 'draft']);
        $other = $this->variant(Brand::factory()->create());

        $this->actingAs($this->admin())->post(route('admin.discounts.store'), $this->form(['targets' => ['brand' => [$coolmate->id]]]))
            ->assertRedirect(route('admin.discounts.index', ['loai' => 'variant']))
            ->assertSessionHas('status', 'Đã tạo giảm giá cho 2 biến thể.');

        $this->assertEqualsCanonicalizing($onSale->pluck('id')->all(), Discount::query()->pluck('product_variant_id')->all());
        $this->assertSame(['variant'], Discount::query()->pluck('scope')->unique()->values()->all());
        $this->assertEquals(20, Discount::query()->first()->discount_value);
        $this->assertNotContains($hidden->id, Discount::query()->pluck('product_variant_id')->all());
        $this->assertNotContains($draft->id, Discount::query()->pluck('product_variant_id')->all());
        $this->assertNotContains($other->id, Discount::query()->pluck('product_variant_id')->all());
    }

    public function test_brands_products_and_variants_picked_together_never_make_a_variant_twice(): void
    {
        $coolmate = Brand::factory()->create();
        $a = $this->variant($coolmate, variant: ['size' => 'M', 'color' => 'Đen']);
        $b = ProductVariant::factory()->for($a->product)->create(['size' => 'L', 'color' => 'Đen']);
        $c = $this->variant(Brand::factory()->create());
        $d = $this->variant(Brand::factory()->create());

        $this->actingAs($this->admin())->post(route('admin.discounts.store'), $this->form(['targets' => [
            'brand' => [$coolmate->id], 'product' => [$a->product_id, $c->product_id], 'variant' => [$a->id, $c->id],
        ]]))->assertSessionHasNoErrors();

        $ids = Discount::query()->pluck('product_variant_id')->sort()->values()->all();
        $this->assertSame(collect([$a->id, $b->id, $c->id])->sort()->values()->all(), $ids);
        $this->assertNotContains($d->id, $ids);
    }

    public function test_a_variant_discount_still_works_with_one_variant_and_no_picks(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.discounts.store'), $this->form(['product_variant_id' => $variant->id]))
            ->assertRedirect(route('admin.discounts.index'));

        $this->assertSame($variant->id, Discount::query()->sole()->product_variant_id);
    }

    public function test_a_variant_discount_needs_something_picked_and_something_on_sale(): void
    {
        $admin = $this->admin();
        $brand = Brand::factory()->create();
        $this->variant($brand, variant: ['is_active' => false]);

        $this->actingAs($admin)->post(route('admin.discounts.store'), $this->form())
            ->assertSessionHasErrors(['product_variant_id' => 'Hãy chọn thương hiệu, sản phẩm hoặc biến thể để giảm giá.']);
        $this->actingAs($admin)->post(route('admin.discounts.store'), $this->form(['targets' => ['brand' => [$brand->id]]]))
            ->assertSessionHasErrors(['targets' => 'Không có biến thể nào đang bán thuộc các mục đã chọn.']);
        $this->actingAs($admin)->post(route('admin.discounts.store'), $this->form(['targets' => ['brand' => [999999]]]))
            ->assertSessionHasErrors('targets.brand.0');

        $this->assertSame(0, Discount::query()->count());
    }

    public function test_the_shared_rules_still_apply_to_a_bulk_creation(): void
    {
        $brand = Brand::factory()->create();
        $this->variant($brand);

        $this->actingAs($this->admin())->post(route('admin.discounts.store'), $this->form(['discount_value' => 150, 'targets' => ['brand' => [$brand->id]]]))
            ->assertSessionHasErrors('discount_value');

        $this->assertSame(0, Discount::query()->count());
    }

    public function test_an_order_voucher_can_be_limited_and_then_opened_up_again(): void
    {
        $admin = $this->admin();
        $brand = Brand::factory()->create();
        $variant = $this->variant($brand);
        $voucher = $this->form([
            'scope' => 'order', 'name' => 'Giảm 10% Coolmate', 'code' => 'cool10', 'discount_type' => 'percent', 'discount_value' => 10,
            'applies_to' => 'selected', 'targets' => ['brand' => [$brand->id, $brand->id], 'variant' => [$variant->id]],
        ]);

        $this->actingAs($admin)->post(route('admin.discounts.store'), $voucher)->assertRedirect(route('admin.discounts.index'));

        $discount = Discount::query()->sole();
        $this->assertSame(['brand' => [$brand->id], 'product' => [], 'variant' => [$variant->id]], $discount->targetIds());
        $this->assertNull($discount->product_variant_id);

        $this->actingAs($admin)->put(route('admin.discounts.update', $discount), ['applies_to' => 'all'] + $voucher)->assertSessionHasNoErrors();
        $this->assertNull($discount->fresh()->targets);
        $this->assertFalse($discount->fresh()->hasTargets());
    }

    public function test_limiting_a_voucher_needs_at_least_one_pick(): void
    {
        $this->actingAs($this->admin())->post(route('admin.discounts.store'), $this->form([
            'scope' => 'order', 'name' => 'Giảm', 'code' => 'GIAM', 'applies_to' => 'selected',
        ]))->assertSessionHasErrors(['targets' => 'Hãy chọn ít nhất một thương hiệu, sản phẩm hoặc biến thể mà voucher áp dụng.']);
    }

    public function test_an_update_that_does_not_mention_applies_to_leaves_the_limit_alone(): void
    {
        $brand = Brand::factory()->create();
        $discount = Discount::factory()->coupon()->create(['code' => 'KEEP', 'name' => 'Giữ', 'targets' => ['brand' => [$brand->id]]]);

        // What the Excel import and the assistant send: no applies_to, no targets.
        $this->actingAs($this->admin())->put(route('admin.discounts.update', $discount), $this->form([
            'scope' => 'order', 'code' => 'KEEP', 'name' => 'Giữ lại', 'discount_type' => 'fixed', 'discount_value' => 70000,
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Giữ lại', $discount->fresh()->name);
        $this->assertSame([$brand->id], $discount->fresh()->targetIds()['brand']);
    }

    public function test_a_shipping_voucher_ignores_picks(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.discounts.store'), $this->form([
            'scope' => 'shipping', 'name' => 'Freeship', 'is_auto_apply' => 1, 'discount_value' => 100,
            'applies_to' => 'selected', 'targets' => ['brand' => [$brand->id]],
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Discount::query()->sole()->targets);
    }

    public function test_the_picker_is_on_the_new_variant_form_and_the_order_voucher_form_only(): void
    {
        $admin = $this->admin();
        $brand = Brand::factory()->create(['name' => 'Coolmate']);
        $variant = $this->variant($brand, product: ['name' => 'Áo khoác gió']);
        $voucher = Discount::factory()->coupon()->create(['name' => 'Giảm Coolmate', 'targets' => ['brand' => [$brand->id]]]);

        $this->actingAs($admin)->get(route('admin.discounts.create', ['loai' => 'variant']))
            ->assertOk()->assertSee('discountTargets', false)->assertSee('Coolmate')->assertSee('Áo khoác gió')->assertDontSee('— Chọn biến thể —');
        $this->actingAs($admin)->get(route('admin.discounts.create', ['loai' => 'order']))
            ->assertOk()->assertSee('Toàn bộ đơn hàng')->assertSee('discountTargets', false);
        $this->actingAs($admin)->get(route('admin.discounts.edit', $voucher))
            ->assertOk()->assertSee('name="applies_to" value="selected"', false)->assertSee('discountTargets', false);
        $this->actingAs($admin)->get(route('admin.discounts.create', ['loai' => 'shipping']))
            ->assertOk()->assertDontSee('discountTargets', false);

        $single = Discount::factory()->create(['product_variant_id' => $variant->id]);
        $this->actingAs($admin)->get(route('admin.discounts.edit', $single))
            ->assertOk()->assertSee('— Chọn biến thể —', false)->assertDontSee('discountTargets', false);
    }

    public function test_the_list_and_the_cart_say_what_a_voucher_covers(): void
    {
        $brand = Brand::factory()->create(['name' => 'Coolmate']);
        // In stock for certain: the factory's random stock can be 0, which keeps it out of the cart.
        $variant = $this->variant($brand, 300000, variant: ['stock_quantity' => 20]);
        Discount::factory()->coupon()->automatic()->create(['name' => 'Giảm 10% Coolmate', 'discount_type' => 'percent', 'discount_value' => 10, 'targets' => ['brand' => [$brand->id]]]);

        $this->actingAs($this->admin())->get(route('admin.discounts.index'))->assertOk()->assertSee('Áp dụng cho thương hiệu Coolmate');

        $this->post(route('cart.store'), ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $this->get(route('cart.index'))->assertOk()->assertSee('Giảm 10% Coolmate chỉ giảm trên thương hiệu Coolmate.');
    }
}
