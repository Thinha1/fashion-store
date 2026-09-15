<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFeaturedTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_featured_state_repeatedly_without_changing_product_or_variants(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['name' => 'Áo thun kiểm thử']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 12]);

        foreach ([true, true, false] as $featured) {
            $this->actingAs($admin)->patchJson(route('admin.products.featured', $product), [
                'is_featured' => $featured,
                'name' => 'Không được thay tên',
                'variants' => [],
            ])->assertOk()->assertExactJson(['is_featured' => $featured]);

            $this->assertSame($featured, $product->fresh()->is_featured);
            $this->get(route('home'))->assertViewHas('featured', fn ($products) => $products->contains('id', $product->id) === $featured);
        }

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Áo thun kiểm thử', 'updated_by' => $admin->id]);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock_quantity' => 12, 'deleted_at' => null]);
        $this->get(route('admin.products.index'))->assertOk()
            ->assertSee(route('admin.products.featured', $product), false)
            ->assertSee('aria-pressed="false"', false);
    }

    public function test_star_form_returns_to_the_current_page_without_javascript(): void
    {
        $product = Product::factory()->featured()->create();
        $page = route('admin.products.index', ['page' => 2]);

        $this->actingAs(User::factory()->admin()->create())->from($page)
            ->post(route('admin.products.featured', $product), ['_method' => 'PATCH', 'is_featured' => '0'])
            ->assertRedirect($page)->assertSessionHas('status');

        $this->assertFalse($product->fresh()->is_featured);
    }

    public function test_guest_cannot_change_featured_state(): void
    {
        $product = Product::factory()->create();

        $this->patchJson(route('admin.products.featured', $product), ['is_featured' => true])->assertUnauthorized();
        $this->assertFalse($product->fresh()->is_featured);
    }

    public function test_customer_cannot_change_featured_state(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.products.featured', $product), ['is_featured' => true])->assertForbidden();
        $this->assertFalse($product->fresh()->is_featured);
    }

    public function test_unverified_admin_cannot_change_featured_state(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->admin()->unverified()->create())
            ->patchJson(route('admin.products.featured', $product), ['is_featured' => true])->assertForbidden();
        $this->assertFalse($product->fresh()->is_featured);
    }

    public function test_missing_or_invalid_featured_state_is_rejected(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        foreach ([[], ['is_featured' => 'yes'], ['is_featured' => null]] as $payload) {
            $this->patchJson(route('admin.products.featured', $product), $payload)->assertUnprocessable()
                ->assertJsonValidationErrors('is_featured');
        }

        $this->assertFalse($product->fresh()->is_featured);
    }

    public function test_deleted_product_cannot_be_featured(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson(route('admin.products.featured', $product), ['is_featured' => true])->assertNotFound();
    }
}
