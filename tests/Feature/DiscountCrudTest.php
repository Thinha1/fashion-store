<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function makePayload(array $overrides = []): array
    {
        $variant = ProductVariant::factory()->create();

        return array_merge([
            'product_variant_id' => $variant->id,
            'discount_type' => 'percent',
            'discount_value' => 10,
            'starts_at' => now()->subDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'is_active' => '1',
        ], $overrides);
    }

    public function test_customer_cannot_access_discount_index(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.discounts.index'))->assertForbidden();
    }

    public function test_admin_can_view_discount_list(): void
    {
        $discount = Discount::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.discounts.index'))
            ->assertOk();
    }

    public function test_admin_can_create_variant_discount(): void
    {
        $variant = ProductVariant::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.discounts.store'), $this->makePayload([
            'product_variant_id' => $variant->id,
        ]));

        $this->assertDatabaseHas('discounts', [
            'product_variant_id' => $variant->id,
            'scope' => 'variant',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.discounts.index'));
    }

    public function test_variant_discount_cannot_have_code(): void
    {
        $variant = ProductVariant::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.discounts.store'), $this->makePayload([
            'product_variant_id' => $variant->id,
            'code' => 'SHOULD-FAIL',
        ]));

        $response->assertSessionHasErrors(['code']);
        $this->assertDatabaseCount('discounts', 0);
    }

    public function test_percent_discount_value_cannot_exceed_100(): void
    {
        $variant = ProductVariant::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.discounts.store'), $this->makePayload([
            'product_variant_id' => $variant->id,
            'discount_type' => 'percent',
            'discount_value' => 150,
        ]));

        $response->assertSessionHasErrors(['discount_value']);
    }

    public function test_fixed_discount_value_can_exceed_100(): void
    {
        $variant = ProductVariant::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.discounts.store'), $this->makePayload([
            'product_variant_id' => $variant->id,
            'discount_type' => 'fixed',
            'discount_value' => 50000,
        ]));

        $response->assertRedirect(route('admin.discounts.index'));
        $this->assertDatabaseHas('discounts', [
            'product_variant_id' => $variant->id,
            'discount_type' => 'fixed',
            'discount_value' => 50000,
        ]);
    }

    public function test_admin_can_update_variant_discount(): void
    {
        $discount = Discount::factory()->create();

        $response = $this->actingAs($this->admin())->put(route('admin.discounts.update', $discount), $this->makePayload([
            'product_variant_id' => $discount->product_variant_id,
            'discount_value' => 25,
        ]));

        $this->assertDatabaseHas('discounts', [
            'id' => $discount->id,
            'discount_value' => 25,
        ]);

        $response->assertRedirect(route('admin.discounts.index'));
    }

    public function test_admin_can_delete_discount(): void
    {
        $discount = Discount::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.discounts.destroy', $discount));

        $response->assertRedirect(route('admin.discounts.index'));
        $this->assertDatabaseMissing('discounts', ['id' => $discount->id]);
    }

    public function test_ends_at_must_be_after_starts_at(): void
    {
        $variant = ProductVariant::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.discounts.store'), $this->makePayload([
            'product_variant_id' => $variant->id,
            'starts_at' => now()->format('Y-m-d\TH:i'),
            'ends_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ]));

        $response->assertSessionHasErrors(['ends_at']);
    }
}
