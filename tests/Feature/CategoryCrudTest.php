<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_customer_cannot_access_category_index(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.categories.index'))->assertForbidden();
    }

    public function test_admin_can_view_category_list(): void
    {
        $parent = Category::factory()->create(['name' => 'Nam']);
        $child = Category::factory()->create(['name' => 'Áo Thun Nam']);

        $child->update(['parent_id' => $parent->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Nam')
            ->assertSee('Áo Thun Nam');
    }

    public function test_admin_can_create_root_category(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.categories.store'), [
            'name' => 'Nam',
            'slug' => 'nam',
            'is_active' => '1',
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('categories', ['name' => 'Nam', 'slug' => 'nam', 'parent_id' => null]);
        $response->assertRedirect(route('admin.categories.index'));
    }

    public function test_admin_can_create_child_category(): void
    {
        $parent = Category::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.categories.store'), [
            'name' => 'Áo Thun',
            'slug' => 'ao-thun',
            'parent_id' => $parent->id,
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('categories', ['name' => 'Áo Thun', 'parent_id' => $parent->id]);
        $response->assertRedirect(route('admin.categories.index'));
    }

    public function test_category_cannot_be_its_own_parent(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin())->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'slug' => $category->slug,
            'parent_id' => $category->id,
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['parent_id']);
    }

    public function test_admin_can_update_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin())->put(route('admin.categories.update', $category), [
            'name' => 'Updated',
            'slug' => $category->slug,
            'is_active' => '0',
            'sort_order' => 5,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated',
            'is_active' => false,
            'sort_order' => 5,
        ]);

        $response->assertRedirect(route('admin.categories.index'));
    }

    public function test_admin_cannot_delete_category_with_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($this->admin())->delete(route('admin.categories.destroy', $category));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_admin_cannot_delete_category_with_children(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        $response = $this->actingAs($this->admin())->delete(route('admin.categories.destroy', $parent));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
    }

    public function test_admin_can_delete_empty_leaf_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.categories.destroy', $category));

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
