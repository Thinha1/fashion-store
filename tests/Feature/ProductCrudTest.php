<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function makePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Áo Thun Cơ Bản',
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
            'description' => 'Áo thun chất liệu cotton 100%',
            'base_price' => 199000,
            'status' => 'active',
            'is_featured' => '1',
            'variants' => [
                ['size' => 'S', 'color' => 'Đen', 'sku' => 'ATCB-S-ĐEN', 'price' => 199000, 'stock_quantity' => 10, 'low_stock_threshold' => 5, 'is_active' => '1'],
                ['size' => 'M', 'color' => 'Trắng', 'sku' => 'ATCB-M-TRANG', 'price' => 199000, 'stock_quantity' => 20, 'low_stock_threshold' => 5, 'is_active' => '1'],
            ],
        ], $overrides);
    }

    public function test_customer_cannot_access_product_index(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.products.index'))->assertForbidden();
    }

    public function test_admin_can_view_product_list(): void
    {
        $product = Product::factory()->create(['name' => 'Áo Khoác Bomber']);

        $this->actingAs($this->admin())
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Áo Khoác Bomber');
    }

    public function test_admin_can_create_product_with_variants(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.products.store'), $this->makePayload());

        $product = Product::query()->where('slug', 'ao-thun-co-ban')->firstOrFail();

        $response->assertRedirect(route('admin.products.show', $product));

        $this->assertSame(2, $product->variants()->count());
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'size' => 'S',
            'color' => 'Đen',
            'sku' => 'ATCB-S-ĐEN',
            'stock_quantity' => 10,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'size' => 'M',
            'color' => 'Trắng',
            'sku' => 'ATCB-M-TRANG',
            'stock_quantity' => 20,
        ]);
    }

    public function test_slug_gets_a_numeric_suffix_when_the_base_slug_is_taken(): void
    {
        // Different names (so no other rule blocks this) that slugify to
        // the same base string.
        Product::factory()->create(['name' => 'Áo Thun Cơ Bản', 'slug' => 'ao-thun-co-ban']);

        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->makePayload(['name' => 'Áo Thun Cơ Bản!']));

        $this->assertDatabaseHas('products', ['name' => 'Áo Thun Cơ Bản!', 'slug' => 'ao-thun-co-ban-2']);
    }

    public function test_product_variant_sku_cannot_collide_with_other_product(): void
    {
        $otherProduct = Product::factory()->create();
        ProductVariant::factory()->create([
            'product_id' => $otherProduct->id,
            'sku' => 'SHARED-SKU-123',
            'size' => 'S',
            'color' => 'Xanh',
        ]);

        $payload = $this->makePayload();
        $payload['variants'][0]['sku'] = 'SHARED-SKU-123';

        $response = $this->actingAs($this->admin())->post(route('admin.products.store'), $payload);

        $response->assertSessionHasErrors(['variants.0.sku']);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_product_variant_sku_cannot_duplicate_within_request(): void
    {
        $payload = $this->makePayload();
        $payload['variants'][0]['sku'] = 'DUPE-SKU';
        $payload['variants'][1]['sku'] = 'DUPE-SKU';

        $response = $this->actingAs($this->admin())->post(route('admin.products.store'), $payload);

        // The first occurrence (index 0) is kept; the duplicate (index 1) gets the error.
        $response->assertSessionHasErrors(['variants.1.sku']);
    }

    public function test_product_can_upload_images(): void
    {
        Storage::fake('s3');

        $payload = $this->makePayload();
        $payload['images'] = [
            UploadedFile::fake()->image('photo1.jpg', 800, 800),
            UploadedFile::fake()->image('photo2.png', 600, 600),
        ];

        $response = $this->actingAs($this->admin())->post(route('admin.products.store'), $payload);

        $product = Product::query()->where('slug', 'ao-thun-co-ban')->firstOrFail();
        $this->assertSame(2, $product->images()->count());

        $images = $product->images()->get();
        Storage::disk('s3')->assertExists($images[0]->path);
        Storage::disk('s3')->assertExists($images[1]->path);
    }

    public function test_product_image_rejects_non_image_file(): void
    {
        $payload = $this->makePayload();
        $payload['images'] = [
            UploadedFile::fake()->create('malware.php', 100, 'application/octet-stream'),
        ];

        $response = $this->actingAs($this->admin())->post(route('admin.products.store'), $payload);

        $response->assertSessionHasErrors(['images.0']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_admin_can_update_product(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $payload = $this->makePayload([
            'name' => 'Updated Name',
            'slug' => $product->slug,
            'base_price' => 249000,
            'variants' => [
                $variant->id => [
                    'size' => 'L',
                    'color' => 'Đỏ',
                    'sku' => $variant->sku,
                    'price' => 249000,
                    'stock_quantity' => 99,
                    'low_stock_threshold' => 3,
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.products.update', $product), $payload);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'base_price' => 249000,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'size' => 'L',
            'color' => 'Đỏ',
            'stock_quantity' => 99,
            'low_stock_threshold' => 3,
        ]);

        $response->assertRedirect(route('admin.products.show', $product));
    }

    public function test_admin_can_remove_a_variant_by_omitting_it_from_the_update(): void
    {
        // Mirrors the "×" button: it just removes that row's inputs from the
        // form before submit, so the variant it belonged to is missing from
        // $request->variants entirely.
        $product = Product::factory()->create();
        $kept = ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 'S', 'color' => 'Đen']);
        $removed = ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 'M', 'color' => 'Trắng']);

        $payload = $this->makePayload([
            'name' => $product->name,
            'variants' => [
                $kept->id => [
                    'size' => $kept->size,
                    'color' => $kept->color,
                    'sku' => $kept->sku,
                    'stock_quantity' => $kept->stock_quantity,
                    'low_stock_threshold' => $kept->low_stock_threshold,
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.products.update', $product), $payload);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, $product->variants()->count());
        $this->assertSoftDeleted('product_variants', ['id' => $removed->id]);
        $this->assertDatabaseHas('product_variants', ['id' => $kept->id, 'deleted_at' => null]);
    }

    public function test_admin_can_add_a_new_variant_row_alongside_an_existing_one(): void
    {
        // Mirrors what the "+ Thêm biến thể" button's JS actually submits:
        // the existing variant keyed by its real id, plus a new row keyed
        // "new-0" (non-numeric on purpose, see products/_form.blade.php) so
        // it can never collide with a real variant id and get treated as an
        // update instead of a create.
        $product = Product::factory()->create();
        $existingVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'EXISTING-SKU']);

        $payload = $this->makePayload([
            'name' => $product->name,
            'variants' => [
                $existingVariant->id => [
                    'size' => $existingVariant->size,
                    'color' => $existingVariant->color,
                    'sku' => $existingVariant->sku,
                    'stock_quantity' => 5,
                    'low_stock_threshold' => 5,
                ],
                'new-0' => [
                    'size' => 'XL',
                    'color' => 'Vàng',
                    'sku' => 'NEW-VARIANT-SKU',
                    'stock_quantity' => 7,
                    'low_stock_threshold' => 5,
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.products.update', $product), $payload);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(2, $product->variants()->count());
        $this->assertDatabaseHas('product_variants', [
            'id' => $existingVariant->id,
            'sku' => 'EXISTING-SKU',
            'stock_quantity' => 5,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'NEW-VARIANT-SKU',
            'size' => 'XL',
            'color' => 'Vàng',
            'stock_quantity' => 7,
        ]);
    }

    public function test_admin_can_soft_delete_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.products.destroy', $product));

        $response->assertRedirect(route('admin.products.index'));
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
