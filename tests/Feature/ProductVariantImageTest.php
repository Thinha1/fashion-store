<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Tests\TestCase;

class ProductVariantImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        $this->actingAs(User::factory()->admin()->create());
    }

    private function variant(array $overrides = []): array
    {
        return array_replace(['size' => 'M', 'color' => 'Đen', 'sku' => 'BLACK-M', 'stock_quantity' => 10, 'low_stock_threshold' => 5], $overrides);
    }

    private function payload(Product $product, array $overrides = []): array
    {
        return array_replace($product->only('name', 'category_id', 'brand_id', 'base_price', 'status'), $overrides);
    }

    private function image(Product $product, ?ProductVariant $variant = null): ProductImage
    {
        $path = 'products/'.fake()->uuid().'.jpg';
        Storage::disk('s3')->put($path, 'existing-image');

        return $product->images()->create(['path' => $path, 'product_variant_id' => $variant?->id]);
    }

    public function test_create_stores_multiple_images_for_each_new_variant_and_shared_images(): void
    {
        $product = Product::factory()->make();
        $this->post(route('admin.products.store'), $this->payload($product, [
            'variants' => [
                'new-0' => $this->variant(['images' => [UploadedFile::fake()->image('black-front.jpg'), UploadedFile::fake()->image('black-back.png')]]),
                'new-3' => $this->variant(['color' => 'Trắng', 'sku' => 'WHITE-M', 'images' => [UploadedFile::fake()->image('white.jpg')]]),
            ],
            'images' => [UploadedFile::fake()->image('size-chart.jpg')],
        ]))->assertSessionHasNoErrors();

        $created = Product::query()->sole();
        $black = $created->variants()->where('sku', 'BLACK-M')->sole();
        $white = $created->variants()->where('sku', 'WHITE-M')->sole();
        $this->assertCount(2, $black->images);
        $this->assertCount(1, $white->images);
        $this->assertSame(1, $created->images()->whereNull('product_variant_id')->count());
        $this->assertSame(1, $created->images()->where('is_primary', true)->count());
        foreach ($created->images as $image) {
            Storage::disk('s3')->assertExists($image->path);
            $this->assertSame($created->id, $image->product_id);
        }
    }

    public function test_edit_uploads_to_existing_and_new_variants_without_changing_shared_images(): void
    {
        $product = Product::factory()->create();
        $existing = ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 'M', 'color' => 'Đen']);
        $shared = $this->image($product);
        $oldImage = $this->image($product, $existing);
        $payload = $this->payload($product, ['variants' => [
            $existing->id => $this->variant(['sku' => $existing->sku, 'images' => [UploadedFile::fake()->image('detail.jpg')]]),
            'new-0' => $this->variant(['size' => 'L', 'sku' => 'BLACK-L', 'images' => [UploadedFile::fake()->image('large.jpg')]]),
        ]]);
        $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
        $new = $product->variants()->where('sku', 'BLACK-L')->sole();
        $this->assertCount(1, $new->images);
        $this->assertCount(2, $existing->fresh()->images);
        $this->assertNull($shared->fresh()->product_variant_id);
        $this->assertSame($existing->id, $oldImage->fresh()->product_variant_id);
        $response = $this->get(route('admin.products.edit', $product))->assertOk()->assertDontSee('Thuộc biến thể')->assertDontSee('image_variants[');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $variantPhotos = $xpath->query('//div[@data-variant-key="'.$existing->id.'"]//img[@src]');
        $this->assertSame(2, $variantPhotos->length);
        $this->assertSame(Storage::disk('s3')->url($oldImage->path), $variantPhotos->item(0)->getAttribute('src'));
    }

    public function test_edit_preserves_links_and_removed_variant_images_become_shared(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $image = $this->image($product, $variant);
        $payload = $this->payload($product, ['variants' => [$variant->id => $this->variant(['sku' => $variant->sku])]]);
        $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
        $this->assertSame($variant->id, $image->fresh()->product_variant_id);

        $payload['variants'] = [];
        $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
        $this->assertSoftDeleted($variant);
        $this->assertNull($image->fresh()->product_variant_id);
        Storage::disk('s3')->assertExists($image->path);
    }

    public function test_invalid_variant_upload_rejects_all_images_and_renders_errors_with_old_rows(): void
    {
        $product = Product::factory()->create();
        $this->image($product);
        $payload = $this->payload($product, [
            'variants' => ['new-4' => $this->variant(['images' => [
                UploadedFile::fake()->image('valid.jpg'),
                UploadedFile::fake()->create('script.php', 1, 'application/x-php'),
                UploadedFile::fake()->image('large.jpg')->size(4097),
            ]])],
        ]);
        $this->from(route('admin.products.edit', $product))->put(route('admin.products.update', $product), $payload)
            ->assertRedirect(route('admin.products.edit', $product));
        $this->assertSame(0, $product->variants()->count());
        $this->assertCount(1, Storage::disk('s3')->allFiles());
        $response = $this->get(route('admin.products.edit', $product))->assertOk();
        $response->assertSee('data-variant-key="new-4"', false)->assertSee('BLACK-M')->assertSee('Ảnh biến thể không được vượt quá 4MB.');
    }

    public function test_create_validation_keeps_new_variant_fields(): void
    {
        $product = Product::factory()->make();
        $this->from(route('admin.products.create'))->post(route('admin.products.store'), $this->payload($product, [
            'name' => '', 'variants' => ['new-7' => $this->variant()],
        ]))->assertSessionHasErrors('name');
        $this->get(route('admin.products.create'))->assertOk()->assertSee('data-variant-key="new-7"', false)->assertSee('BLACK-M');
    }

    public function test_deleting_shared_and_variant_images_removes_files_and_replaces_the_cover(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $cover = $this->image($product);
        $cover->update(['is_primary' => true]);
        $specific = $this->image($product, $variant);
        $kept = $this->image($product);
        $payload = $this->payload($product, [
            'variants' => [$variant->id => $this->variant(['sku' => $variant->sku])],
            'removed_images' => [$cover->id, $specific->id],
        ]);
        $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
        $this->assertModelMissing($cover);
        $this->assertModelMissing($specific);
        Storage::disk('s3')->assertMissing([$cover->path, $specific->path]);
        Storage::disk('s3')->assertExists($kept->path);
        $this->assertTrue($kept->fresh()->is_primary);

        $payload['removed_images'] = [$kept->id];
        $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
        $this->assertSame(0, $product->images()->count());
        $payload['removed_images'] = [];
        $payload['variants'][$variant->id]['images'] = [UploadedFile::fake()->image('replacement.jpg')];
        $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
        $this->assertTrue($product->images()->sole()->is_primary);
    }

    public function test_deleting_an_image_keeps_a_file_still_used_by_another_product(): void
    {
        $product = Product::factory()->create();
        $image = $this->image($product);
        $other = Product::factory()->create()->images()->create(['path' => $image->path]);
        $this->put(route('admin.products.update', $product), $this->payload($product, ['removed_images' => [$image->id]]))
            ->assertSessionHasNoErrors();
        $this->assertModelMissing($image);
        $this->assertModelExists($other);
        Storage::disk('s3')->assertExists($image->path);
    }

    public function test_deletion_rejects_foreign_images_and_keeps_pending_removals_after_invalid_input(): void
    {
        $product = Product::factory()->create();
        $own = $this->image($product);
        $foreign = $this->image(Product::factory()->create());
        $this->put(route('admin.products.update', $product), $this->payload($product, ['removed_images' => [$foreign->id, $own->id]]))
            ->assertSessionHasErrors('removed_images.0');
        $this->assertModelExists($own);
        $this->assertModelExists($foreign);
        Storage::disk('s3')->assertExists([$own->path, $foreign->path]);

        $this->from(route('admin.products.edit', $product))->put(route('admin.products.update', $product), $this->payload($product, [
            'name' => '', 'removed_images' => [(string) $own->id],
        ]))->assertRedirect(route('admin.products.edit', $product));
        $this->get(route('admin.products.edit', $product))->assertOk()
            ->assertSee('Chưa thể lưu')->assertSee('productForm('.Js::from([(string) $own->id]).')', false);
        $this->assertModelExists($own);
    }

    public function test_storefront_renders_variant_image_links_and_only_active_variants(): void
    {
        $product = Product::factory()->create(['status' => 'active']);
        $active = ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 'M', 'color' => "Xanh 'đậm'", 'is_active' => true]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 'L', 'color' => 'Đen', 'is_active' => false]);
        $image = $this->image($product, $active);
        $this->image($product);
        $response = $this->get(route('products.show', $product))->assertOk()->assertSee('productDetail(');
        $this->assertEquals([$active->id], $response->viewData('product')->variants->modelKeys());
        $this->assertSame($active->id, $response->viewData('product')->images->find($image->id)->product_variant_id);
        $this->get(route('admin.products.show', $product))->assertOk()->assertSee('Ảnh chung')->assertSee("M · Xanh 'đậm'");
    }
}
