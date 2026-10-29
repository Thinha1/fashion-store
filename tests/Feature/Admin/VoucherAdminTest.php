<?php

namespace Tests\Feature\Admin;

use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function voucher(array $overrides = []): array
    {
        return array_merge([
            'scope' => 'shipping',
            'name' => 'Miễn phí vận chuyển đơn từ 500.000 ₫',
            'discount_type' => 'percent',
            'discount_value' => 100,
            'min_order_amount' => 500000,
            'starts_at' => now()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'is_active' => 1,
        ], $overrides);
    }

    public function test_an_automatic_shipping_voucher_needs_no_code(): void
    {
        $this->actingAs($this->admin())->post(route('admin.discounts.store'), $this->voucher(['is_auto_apply' => 1]))
            ->assertRedirect(route('admin.discounts.index'));

        $voucher = Discount::query()->sole();
        $this->assertSame('shipping', $voucher->scope);
        $this->assertTrue($voucher->is_auto_apply);
        $this->assertNull($voucher->code);
        $this->assertNull($voucher->product_variant_id);
    }

    public function test_a_voucher_the_customer_types_needs_a_unique_code_stored_upper_case(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.discounts.store'), $this->voucher())
            ->assertSessionHasErrors(['code' => 'Voucher không tự động áp dụng cần có mã để khách nhập.']);

        $this->actingAs($admin)->post(route('admin.discounts.store'), $this->voucher(['scope' => 'order', 'code' => ' giam50k ', 'discount_type' => 'fixed', 'discount_value' => 50000]))
            ->assertSessionHasNoErrors();
        $this->assertSame('GIAM50K', Discount::query()->sole()->code);

        $this->actingAs($admin)->post(route('admin.discounts.store'), $this->voucher(['code' => 'GIAM50K']))
            ->assertSessionHasErrors('code');
        $this->actingAs($admin)->post(route('admin.discounts.store'), $this->voucher(['code' => 'GIẢM 50K']))
            ->assertSessionHasErrors('code');
    }

    public function test_voucher_fields_never_stick_to_a_variant_discount_and_the_kind_is_kept_on_edit(): void
    {
        $admin = $this->admin();
        $variant = ProductVariant::factory()->create();

        $this->actingAs($admin)->post(route('admin.discounts.store'), $this->voucher([
            'scope' => 'variant', 'product_variant_id' => $variant->id, 'discount_value' => 10, 'is_auto_apply' => 1, 'usage_limit' => 5,
        ]))->assertSessionHasNoErrors();

        $discount = Discount::query()->sole();
        $this->assertSame('variant', $discount->scope);
        $this->assertFalse($discount->is_auto_apply);
        $this->assertNull($discount->usage_limit);
        $this->assertNull($discount->min_order_amount);

        // Posting another kind on edit does not change what was saved.
        $this->actingAs($admin)->put(route('admin.discounts.update', $discount), $this->voucher([
            'scope' => 'shipping', 'product_variant_id' => $variant->id, 'discount_value' => 15,
        ]))->assertSessionHasNoErrors();
        $this->assertSame('variant', $discount->fresh()->scope);
        $this->assertSame('15.00', $discount->fresh()->discount_value);
    }

    public function test_every_kind_can_be_edited_from_the_same_screen(): void
    {
        $voucher = Discount::factory()->shippingVoucher()->automatic()->create(['name' => 'Freeship cuối tuần']);

        $this->actingAs($this->admin())->get(route('admin.discounts.edit', $voucher))
            ->assertOk()
            ->assertSee('Voucher giảm phí ship')
            ->assertSee('Freeship cuối tuần')
            ->assertSee('name="is_auto_apply" value="1" checked', false);
    }

    public function test_the_list_filters_by_kind_status_and_text(): void
    {
        $product = Product::factory()->create(['name' => 'Áo khoác gió']);
        Discount::factory()->for(ProductVariant::factory()->for($product))->create();
        Discount::factory()->coupon()->create(['product_variant_id' => null, 'code' => 'GIAM50K', 'name' => 'Giảm 50K']);
        Discount::factory()->shippingVoucher()->automatic()->create(['name' => 'Freeship', 'is_active' => false]);
        Discount::factory()->shippingVoucher()->create(['code' => 'SHIPSAU', 'name' => 'Ship sau', 'starts_at' => now()->addWeek(), 'ends_at' => now()->addMonth()]);

        $listed = fn (array $query) => $this->actingAs($this->admin())->get(route('admin.discounts.index', $query))
            ->assertOk()->viewData('discounts')->getCollection()
            ->map(fn (Discount $discount) => $discount->code ?? $discount->name ?? $discount->productVariant->product->name)
            ->sort()->values()->all();

        $this->assertSame(['Freeship', 'SHIPSAU'], $listed(['loai' => 'shipping']));
        $this->assertSame(['Freeship'], $listed(['trang-thai' => 'disabled']));
        $this->assertSame(['SHIPSAU'], $listed(['trang-thai' => 'upcoming']));
        $this->assertSame(['GIAM50K', 'Áo khoác gió'], $listed(['trang-thai' => 'running']));
        $this->assertSame(['GIAM50K'], $listed(['q' => 'giam50']));
        $this->assertSame(['Áo khoác gió'], $listed(['q' => 'khoác']));
        $this->assertCount(4, $listed(['loai' => 'khac', 'trang-thai' => ['x']]));
    }
}
