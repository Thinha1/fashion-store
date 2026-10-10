<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function variant(array $attributes = []): ProductVariant
    {
        return ProductVariant::factory()->create(array_merge(['price' => 200000, 'stock_quantity' => 10], $attributes));
    }

    private function cartItemFor(User $user, ProductVariant $variant, int $quantity = 1): CartItem
    {
        $cart = Cart::query()->create(['user_id' => $user->id, 'status' => 'active']);

        return $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => $quantity]);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('cart.index'))->assertRedirect(route('login'));
        $this->post(route('cart.store'), ['product_variant_id' => $this->variant()->id, 'quantity' => 1])
            ->assertRedirect(route('login'));
    }

    public function test_adding_creates_one_active_cart_and_merges_the_same_variant(): void
    {
        $user = User::factory()->create();
        $variant = $this->variant();

        $this->actingAs($user)->post(route('cart.store'), ['product_variant_id' => $variant->id, 'quantity' => 2])
            ->assertSessionHas('status', 'Đã thêm vào giỏ hàng.');
        $this->actingAs($user)->post(route('cart.store'), ['product_variant_id' => $variant->id, 'quantity' => 3]);

        $cart = $user->activeCart()->sole();
        $this->assertSame(5, $cart->items()->sole()->quantity);
        $this->assertSame(1, $user->carts()->count());
    }

    public function test_cannot_add_more_than_stock(): void
    {
        $user = User::factory()->create();
        $variant = $this->variant(['stock_quantity' => 3]);
        $this->cartItemFor($user, $variant, 2);

        $this->actingAs($user)->post(route('cart.store'), ['product_variant_id' => $variant->id, 'quantity' => 2])
            ->assertSessionHas('error', 'Chỉ còn 3 sản phẩm cho lựa chọn này.');

        $this->assertSame(2, $user->activeCart->items()->sole()->quantity);
    }

    public function test_cannot_add_inactive_or_unpublished_variants(): void
    {
        $user = User::factory()->create();
        $inactive = $this->variant(['is_active' => false]);
        $draft = ProductVariant::factory()->for(Product::factory()->draft())->create(['stock_quantity' => 5]);

        foreach ([$inactive, $draft] as $variant) {
            $this->actingAs($user)->post(route('cart.store'), ['product_variant_id' => $variant->id, 'quantity' => 1])
                ->assertSessionHas('error', 'Sản phẩm này đã ngừng bán.');
        }

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_missing_variant_choice_is_a_validation_error(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('cart.store'), ['quantity' => 1])
            ->assertSessionHasErrors('product_variant_id');
    }

    public function test_cart_page_prices_lines_with_variant_discount_and_shipping(): void
    {
        config(['store.shipping_fee' => 30000]);
        $user = User::factory()->create();
        $variant = $this->variant(['price' => 200000]);
        Discount::factory()->for($variant)->create(['discount_type' => 'percent', 'discount_value' => 10]);
        $this->cartItemFor($user, $variant, 2);

        $this->actingAs($user)->get(route('cart.index'))
            ->assertOk()
            ->assertSee($variant->product->name)
            ->assertViewHas('quote', fn ($quote): bool => $quote->subtotal === 360000 && $quote->grandTotal === 390000);
    }

    public function test_cart_page_flags_lines_that_cannot_be_bought_and_leaves_them_out_of_totals(): void
    {
        $user = User::factory()->create();
        $okay = $this->variant(['price' => 100000]);
        $short = $this->variant(['stock_quantity' => 1]);
        $item = $this->cartItemFor($user, $okay, 1);
        $item->cart->items()->create(['product_variant_id' => $short->id, 'quantity' => 4]);

        $this->actingAs($user)->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Chỉ còn 1 sản phẩm, hãy giảm số lượng.')
            ->assertViewHas('hasProblems', true)
            ->assertViewHas('quote', fn ($quote): bool => $quote->subtotal === 100000);
    }

    public function test_soft_deleted_variant_is_shown_as_discontinued(): void
    {
        $user = User::factory()->create();
        $variant = $this->variant();
        $this->cartItemFor($user, $variant);
        $variant->delete();

        $this->actingAs($user)->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Sản phẩm đã ngừng bán.');
    }

    public function test_updating_quantity_respects_stock(): void
    {
        $user = User::factory()->create();
        $item = $this->cartItemFor($user, $this->variant(['stock_quantity' => 4]));

        $this->actingAs($user)->patch(route('cart.update', $item), ['quantity' => 4])->assertRedirect(route('cart.index'));
        $this->assertSame(4, $item->fresh()->quantity);

        $this->actingAs($user)->patch(route('cart.update', $item), ['quantity' => 5])
            ->assertSessionHas('error', 'Chỉ còn 4 sản phẩm cho lựa chọn này.');
        $this->assertSame(4, $item->fresh()->quantity);
    }

    public function test_removing_a_line(): void
    {
        $user = User::factory()->create();
        $item = $this->cartItemFor($user, $this->variant());

        $this->actingAs($user)->delete(route('cart.destroy', $item))->assertRedirect(route('cart.index'));

        $this->assertModelMissing($item);
    }

    public function test_customer_cannot_change_someone_elses_cart(): void
    {
        $item = $this->cartItemFor(User::factory()->create(), $this->variant());
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->patch(route('cart.update', $item), ['quantity' => 2])->assertForbidden();
        $this->actingAs($intruder)->delete(route('cart.destroy', $item))->assertForbidden();

        $this->assertModelExists($item);
    }

    public function test_header_shows_cart_item_count(): void
    {
        $user = User::factory()->create();
        $this->cartItemFor($user, $this->variant(), 3);

        $this->actingAs($user)->get(route('home'))->assertSee('Giỏ hàng (3 sản phẩm)');
    }
}
