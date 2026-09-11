<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_guest_cannot_access_brand_index(): void
    {
        $this->get(route('admin.brands.index'))->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_brand_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.brands.index'))->assertForbidden();
    }

    public function test_admin_can_view_brand_list(): void
    {
        $brand = Brand::factory()->create(['name' => 'Test Brand']);

        $this->actingAs($this->admin())
            ->get(route('admin.brands.index'))
            ->assertOk()
            ->assertSee('Test Brand');
    }

    public function test_admin_can_create_brand(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.brands.store'), [
            'name' => 'Acme Fashion',
            'slug' => 'acme-fashion',
            'description' => 'A test brand',
            'country' => 'Vietnam',
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('brands', [
            'name' => 'Acme Fashion',
            'slug' => 'acme-fashion',
            'country' => 'Vietnam',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.brands.show', Brand::query()->where('slug', 'acme-fashion')->firstOrFail()));
    }

    public function test_brand_requires_unique_name_and_slug(): void
    {
        Brand::factory()->create(['name' => 'Taken', 'slug' => 'taken']);

        $response = $this->actingAs($this->admin())->post(route('admin.brands.store'), [
            'name' => 'Taken',
            'slug' => 'other-slug',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['name']);

        $response = $this->actingAs($this->admin())->post(route('admin.brands.store'), [
            'name' => 'Another Name',
            'slug' => 'taken',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['slug']);
    }

    public function test_admin_can_update_brand(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->actingAs($this->admin())->put(route('admin.brands.update', $brand), [
            'name' => 'Updated Brand',
            'slug' => $brand->slug,
            'is_active' => '0',
        ]);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Updated Brand',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('admin.brands.show', $brand));
    }

    public function test_admin_cannot_delete_brand_with_products(): void
    {
        $brand = Brand::factory()->create();
        Product::factory()->create(['brand_id' => $brand->id]);

        $response = $this->actingAs($this->admin())->delete(route('admin.brands.destroy', $brand));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('brands', ['id' => $brand->id]);
    }

    public function test_admin_can_delete_brand_without_products(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.brands.destroy', $brand));

        $response->assertRedirect(route('admin.brands.index'));
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }
}
