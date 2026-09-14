<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_admin_can_create_brand_with_auto_generated_slug(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.brands.store'), [
            'name' => 'Acme Fashion',
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

    public function test_slug_gets_a_numeric_suffix_when_the_base_slug_is_taken(): void
    {
        // Different names (so the name-uniqueness rule doesn't block this)
        // that slugify to the same base string.
        Brand::factory()->create(['name' => 'Acme Co', 'slug' => 'acme-co']);

        $this->actingAs($this->admin())->post(route('admin.brands.store'), [
            'name' => 'Acme Co.',
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('brands', ['name' => 'Acme Co.', 'slug' => 'acme-co-2']);
    }

    public function test_client_supplied_slug_is_ignored(): void
    {
        $this->actingAs($this->admin())->post(route('admin.brands.store'), [
            'name' => 'Acme Fashion',
            'slug' => 'a-slug-i-made-up',
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('brands', ['name' => 'Acme Fashion', 'slug' => 'acme-fashion']);
        $this->assertDatabaseMissing('brands', ['slug' => 'a-slug-i-made-up']);
    }

    public function test_brand_requires_unique_name(): void
    {
        Brand::factory()->create(['name' => 'Taken']);

        $response = $this->actingAs($this->admin())->post(route('admin.brands.store'), [
            'name' => 'Taken',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_admin_can_update_brand_and_slug_stays_unchanged(): void
    {
        $brand = Brand::factory()->create(['name' => 'Original Name', 'slug' => 'original-name']);

        $response = $this->actingAs($this->admin())->put(route('admin.brands.update', $brand), [
            'name' => 'Updated Brand',
            'is_active' => '0',
        ]);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Updated Brand',
            'slug' => 'original-name',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('admin.brands.show', $brand));
    }

    public function test_admin_can_upload_a_brand_logo(): void
    {
        Storage::fake('s3');

        $logo = UploadedFile::fake()->image('logo.png');

        $this->actingAs($this->admin())->post(route('admin.brands.store'), [
            'name' => 'Acme Fashion',
            'is_active' => '1',
            'logo' => $logo,
        ]);

        $brand = Brand::query()->where('name', 'Acme Fashion')->firstOrFail();

        $this->assertNotNull($brand->logo_path);
        Storage::disk('s3')->assertExists($brand->logo_path);
    }

    public function test_updating_a_brand_without_a_new_logo_keeps_the_existing_one(): void
    {
        Storage::fake('s3');

        $brand = Brand::factory()->create(['logo_path' => 'brands/existing.png']);
        Storage::disk('s3')->put('brands/existing.png', 'fake-content');

        $this->actingAs($this->admin())->put(route('admin.brands.update', $brand), [
            'name' => $brand->name,
            'is_active' => '1',
        ]);

        $this->assertSame('brands/existing.png', $brand->fresh()->logo_path);
        Storage::disk('s3')->assertExists('brands/existing.png');
    }

    public function test_uploading_a_new_logo_replaces_and_deletes_the_old_one(): void
    {
        Storage::fake('s3');

        $brand = Brand::factory()->create(['logo_path' => 'brands/old.png']);
        Storage::disk('s3')->put('brands/old.png', 'fake-content');

        $this->actingAs($this->admin())->put(route('admin.brands.update', $brand), [
            'name' => $brand->name,
            'is_active' => '1',
            'logo' => UploadedFile::fake()->image('new.png'),
        ]);

        $brand->refresh();

        $this->assertNotSame('brands/old.png', $brand->logo_path);
        Storage::disk('s3')->assertMissing('brands/old.png');
        Storage::disk('s3')->assertExists($brand->logo_path);
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
