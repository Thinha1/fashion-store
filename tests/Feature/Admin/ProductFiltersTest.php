<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFiltersTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function listedNames(array $query): array
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.index', $query))
            ->assertOk()
            ->viewData('products')
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }

    public function test_the_brand_filter_lists_only_that_brands_products(): void
    {
        $nike = Brand::factory()->create(['name' => 'Nike']);
        Product::factory()->create(['name' => 'Áo Nike', 'brand_id' => $nike->id]);
        Product::factory()->create(['name' => 'Áo khác']);

        $this->assertSame(['Áo Nike'], $this->listedNames(['brand' => $nike->id]));
    }

    public function test_a_parent_category_takes_in_the_products_filed_below_it(): void
    {
        $men = Category::factory()->create(['name' => 'Nam', 'parent_id' => null]);
        $menTops = Category::factory()->childOf($men)->create(['name' => 'Áo nam']);
        $tees = Category::factory()->childOf($menTops)->create(['name' => 'Áo thun']);
        $women = Category::factory()->create(['name' => 'Nữ', 'parent_id' => null]);

        Product::factory()->create(['name' => 'Áo thun nam', 'category_id' => $tees->id]);
        Product::factory()->create(['name' => 'Áo nam khác', 'category_id' => $menTops->id]);
        Product::factory()->create(['name' => 'Váy nữ', 'category_id' => $women->id]);

        $this->assertSame(['Áo nam khác', 'Áo thun nam'], $this->listedNames(['category' => $men->id]));
        $this->assertSame(['Áo thun nam'], $this->listedNames(['category' => $tees->id]));
    }

    public function test_brand_and_category_filters_combine(): void
    {
        $nike = Brand::factory()->create();
        $tees = Category::factory()->create();
        Product::factory()->create(['name' => 'Đúng cả hai', 'brand_id' => $nike->id, 'category_id' => $tees->id]);
        Product::factory()->create(['name' => 'Chỉ đúng hãng', 'brand_id' => $nike->id]);
        Product::factory()->create(['name' => 'Chỉ đúng danh mục', 'category_id' => $tees->id]);

        $this->assertSame(['Đúng cả hai'], $this->listedNames(['brand' => $nike->id, 'category' => $tees->id]));
    }

    public function test_unknown_filter_values_are_ignored(): void
    {
        Product::factory()->count(2)->create();

        $this->assertCount(2, $this->listedNames(['brand' => 999999, 'category' => 'abc']));
        $this->assertCount(2, $this->listedNames(['brand' => [Product::query()->value('brand_id')], 'sort' => ['name']]));
    }

    public function test_the_form_keeps_the_choice_and_sort_links_keep_the_filter(): void
    {
        $nike = Brand::factory()->create(['name' => 'Nike']);
        Product::factory()->create(['brand_id' => $nike->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.index', ['brand' => $nike->id]))
            ->assertOk()
            ->assertSee('<option value="'.$nike->id.'" selected>Nike</option>', false)
            ->assertSee('1 sản phẩm khớp')
            ->assertSee('brand='.$nike->id.'&amp;sort=name', false);
    }

    public function test_an_empty_filter_result_says_so(): void
    {
        $brand = Brand::factory()->create();
        Product::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.products.index', ['brand' => $brand->id]))
            ->assertSee('Không có sản phẩm nào khớp bộ lọc.');
    }
}
