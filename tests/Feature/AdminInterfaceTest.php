<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public static function resources(): array
    {
        return [
            [Brand::class, 'brands'], [Category::class, 'categories'],
            [Product::class, 'products'], [Supplier::class, 'suppliers'],
            [Discount::class, 'discounts'], [GoodsReceipt::class, 'goods-receipts'],
        ];
    }

    #[DataProvider('resources')]
    public function test_admin_create_and_edit_forms_render_with_save_and_back_actions(string $model, string $resource): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $record = $model::factory()->create();

        foreach ([route('admin.'.$resource.'.create'), route('admin.'.$resource.'.edit', $record)] as $url) {
            $this->get($url)->assertOk()->assertSee('admin-shell')->assertSee('admin-form-actions')
                ->assertSee('admin-action-primary')->assertSee('admin-action-secondary')
                ->assertSee(route('admin.'.$resource.'.index'));
        }
    }

    public function test_detail_pages_link_to_edit_using_the_shared_primary_action(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach ([Brand::class => 'brands', Product::class => 'products', GoodsReceipt::class => 'goods-receipts'] as $model => $resource) {
            $record = $model::factory()->create();
            $this->get(route('admin.'.$resource.'.show', $record))->assertOk()
                ->assertSee('admin-action-primary')->assertSee('Chỉnh sửa')
                ->assertSee(route('admin.'.$resource.'.edit', $record));
        }
    }

    public function test_confirmed_receipt_has_no_edit_action(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $receipt = GoodsReceipt::factory()->confirmed()->create();
        $this->get(route('admin.goods-receipts.show', $receipt))->assertOk()
            ->assertDontSee(route('admin.goods-receipts.edit', $receipt));
    }

    public function test_product_thumbnail_renders(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create();
        $product->images()->create(['path' => 'products/example.jpg', 'is_primary' => true, 'sort_order' => 0]);
        $this->get(route('admin.products.index'))->assertOk()->assertSee('products/example.jpg');
    }
}
