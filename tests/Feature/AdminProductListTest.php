<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductListTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, array $attributes = [], array $variant = []): Product
    {
        $product = Product::factory()->create(['name' => $name, 'status' => 'active', ...$attributes]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true, 'stock_quantity' => 50, 'low_stock_threshold' => 5, ...$variant]);

        return $product;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_search_looks_through_every_page_by_name_and_sku(): void
    {
        $this->product('Áo sơ mi trắng');
        $this->product('Quần jean xanh', variant: ['sku' => 'QJ-XANH-01']);

        $this->get(route('admin.products.index', ['q' => 'sơ mi']))
            ->assertOk()->assertSee('Áo sơ mi trắng')->assertDontSee('Quần jean xanh');
        $this->get(route('admin.products.index', ['q' => 'QJ-XANH']))
            ->assertSee('Quần jean xanh')->assertDontSee('Áo sơ mi trắng');
    }

    public function test_category_and_brand_filters_narrow_the_list(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $this->product('Áo đúng danh mục', ['category_id' => $category->id, 'brand_id' => $brand->id]);
        $this->product('Áo khác danh mục');

        $this->get(route('admin.products.index', ['category' => $category->id, 'brand' => $brand->id]))
            ->assertSee('Áo đúng danh mục')->assertDontSee('Áo khác danh mục')->assertSee('Xóa lọc');
    }

    public function test_status_tabs_show_counts_and_filter(): void
    {
        $this->product('Áo đang bán');
        $this->product('Áo đã ẩn', ['status' => 'archived']);

        $this->get(route('admin.products.index', ['status' => 'archived']))
            ->assertSee('Áo đã ẩn')->assertDontSee('Áo đang bán')
            ->assertViewHas('counts', fn ($counts) => $counts['all'] === 2 && $counts['active'] === 1 && $counts['archived'] === 1);
    }

    public function test_low_and_out_of_stock_products_are_flagged_and_filterable(): void
    {
        $this->product('Áo còn nhiều');
        $this->product('Áo sắp hết', variant: ['stock_quantity' => 2]);
        $this->product('Áo hết sạch', variant: ['stock_quantity' => 0]);

        $this->get(route('admin.products.index', ['show' => 'low']))
            ->assertSee('Áo sắp hết')->assertDontSee('Áo còn nhiều')->assertDontSee('Áo hết sạch')->assertSee('sắp hết');
        $this->get(route('admin.products.index', ['show' => 'out']))
            ->assertSee('Áo hết sạch')->assertDontSee('Áo sắp hết');
    }

    public function test_dashboard_lists_the_tasks_that_need_attention(): void
    {
        $this->product('Áo sắp hết', variant: ['stock_quantity' => 1]);
        $this->product('Áo hết sạch', variant: ['stock_quantity' => 0]);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('1 sản phẩm sắp hết hàng')
            ->assertSee('1 sản phẩm hết hàng nhưng đang bán')
            ->assertSee('2 sản phẩm chưa có ảnh')
            ->assertSee(route('admin.products.index', ['show' => 'low']), false);
    }

    public function test_dashboard_says_all_is_fine_when_nothing_needs_attention(): void
    {
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Mọi thứ đều ổn');
    }
}
