<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminSortingTest extends TestCase
{
    use RefreshDatabase;

    public static function sortableLists(): array
    {
        return [
            'brands' => [Brand::class, 'brands', 'brands', 'name'],
            'categories' => [Category::class, 'categories', 'categories', 'sort_order'],
            'products' => [Product::class, 'products', 'products', 'name'],
            'suppliers' => [Supplier::class, 'suppliers', 'suppliers', 'name'],
            'discounts' => [Discount::class, 'discounts', 'discounts', 'discount_value'],
            'receipts' => [GoodsReceipt::class, 'goods-receipts', 'receipts', 'total_cost'],
        ];
    }

    #[DataProvider('sortableLists')]
    public function test_sorting_applies_before_pagination_in_both_directions(string $model, string $resource, string $viewKey, string $column): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $ids = [];
        for ($value = 21; $value > 0; $value--) {
            $ids[] = $model::factory()->create([
                $column => $column === 'name' ? sprintf('Item %02d', $value) : $value,
            ])->id;
        }

        foreach (['asc' => array_reverse($ids), 'desc' => $ids] as $direction => $expected) {
            $seen = [];
            foreach ([1, 2, 3] as $page) {
                $response = $this->get(route('admin.'.$resource.'.index', [
                    'sort' => $column, 'direction' => $direction, 'per_page' => 10, 'page' => $page,
                ]))->assertOk();
                $response->assertSee('aria-sort="'.($direction === 'asc' ? 'ascending' : 'descending').'"', false);
                $paginator = $response->viewData($viewKey);
                $seen = array_merge($seen, $paginator->getCollection()->modelKeys());
                $this->assertSame(21, $paginator->total());
                if ($paginator->hasMorePages()) {
                    parse_str(parse_url($paginator->nextPageUrl(), PHP_URL_QUERY), $query);
                    $this->assertSame($column, $query['sort']);
                    $this->assertSame($direction, $query['direction']);
                    $this->assertSame('10', $query['per_page']);
                }
            }
            $this->assertSame($expected, $seen);
        }
    }

    public static function namedRelations(): array
    {
        return [
            'product category' => [Product::class, 'products', 'products', 'category', Category::class, 'category_id'],
            'product brand' => [Product::class, 'products', 'products', 'brand', Brand::class, 'brand_id'],
            'category parent' => [Category::class, 'categories', 'categories', 'parent', Category::class, 'parent_id'],
            'receipt supplier' => [GoodsReceipt::class, 'goods-receipts', 'receipts', 'supplier', Supplier::class, 'supplier_id'],
            'receipt confirmer' => [GoodsReceipt::class, 'goods-receipts', 'receipts', 'confirmed_by', User::class, 'confirmed_by'],
        ];
    }

    #[DataProvider('namedRelations')]
    public function test_related_columns_sort_by_name_instead_of_id(string $model, string $resource, string $viewKey, string $column, string $relatedModel, string $foreignKey): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $ids = [];
        foreach (['Zulu', 'Alpha', 'Bravo'] as $name) {
            $related = $relatedModel::factory()->create(['name' => $name]);
            $ids[] = $model::factory()->create([$foreignKey => $related->id])->id;
        }

        $this->assertSortedIds($resource, $viewKey, $column, [$ids[1], $ids[2], $ids[0]]);
    }

    public static function countedRelations(): array
    {
        return [
            'brand products' => [Brand::class, 'brands', 'brands', 'products_count', Product::class, 'brand_id'],
            'category children' => [Category::class, 'categories', 'categories', 'children_count', Category::class, 'parent_id'],
            'product variants' => [Product::class, 'products', 'products', 'variants_count', ProductVariant::class, 'product_id'],
            'supplier receipts' => [Supplier::class, 'suppliers', 'suppliers', 'goods_receipts_count', GoodsReceipt::class, 'supplier_id'],
        ];
    }

    #[DataProvider('countedRelations')]
    public function test_counts_sort_numerically_and_include_zero(string $model, string $resource, string $viewKey, string $column, string $relatedModel, string $foreignKey): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $ids = [];
        foreach ([2, 0, 10] as $count) {
            $record = $model::factory()->create();
            $ids[] = $record->id;
            $factory = $relatedModel::factory()->count($count);
            if ($relatedModel === ProductVariant::class) {
                $factory = $factory->sequence(fn (Sequence $sequence) => ['size' => 'Size '.$sequence->index]);
            }
            $factory->create([$foreignKey => $record->id]);
        }

        $this->assertSortedIds($resource, $viewKey, $column, [$ids[1], $ids[0], $ids[2]]);
    }

    public function test_discounts_sort_by_product_then_size_and_color_and_exclude_coupons(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create(['name' => 'Alpha']);
        $ids = [];
        foreach ([['M', 'Red'], ['L', 'Red'], ['M', 'Blue']] as [$size, $color]) {
            $variant = ProductVariant::factory()->for($product)->create(compact('size', 'color'));
            $ids[] = Discount::factory()->for($variant, 'productVariant')->create()->id;
        }
        $last = Discount::factory()->for(
            ProductVariant::factory()->for(Product::factory()->state(['name' => 'Zulu'])), 'productVariant'
        )->create();
        $coupon = Discount::factory()->coupon()->create();

        $this->assertSortedIds('discounts', 'discounts', 'variant', [$ids[1], $ids[2], $ids[0], $last->id]);
        $this->get(route('admin.discounts.index', ['sort' => 'variant']))
            ->assertViewHas('discounts', fn ($discounts) => $discounts->total() === 4 && ! $discounts->contains('id', $coupon->id));
    }

    public function test_null_relations_and_deleted_products_do_not_break_sorting(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $draft = GoodsReceipt::factory()->create(['confirmed_by' => null]);
        $confirmed = GoodsReceipt::factory()->confirmed()->create();
        $this->assertSortedIds('goods-receipts', 'receipts', 'confirmed_by', [$draft->id, $confirmed->id]);

        $deleted = Product::factory()->create();
        $variant = ProductVariant::factory()->for($deleted)->create();
        Discount::factory()->for($variant, 'productVariant')->create();
        $deleted->delete();
        $variant->delete();
        $this->get(route('admin.discounts.index', ['sort' => 'variant']))->assertOk();
        $this->get(route('admin.products.index', ['sort' => 'category']))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 0);
    }

    public function test_equal_values_have_stable_order_across_pages(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $products = Product::factory()->count(21)->create(['is_featured' => false]);
        foreach (['asc', 'desc'] as $direction) {
            $seen = [];
            foreach ([1, 2, 3] as $page) {
                $response = $this->get(route('admin.products.index', [
                    'sort' => 'is_featured', 'direction' => $direction, 'per_page' => 10, 'page' => $page,
                ]))->assertOk();
                $seen = array_merge($seen, $response->viewData('products')->getCollection()->modelKeys());
            }
            $expected = $products->modelKeys();
            $this->assertSame($direction === 'asc' ? $expected : array_reverse($expected), $seen);
        }
    }

    public function test_invalid_sort_parameters_use_safe_defaults(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $first = Product::factory()->create(['name' => 'Alpha', 'created_at' => now()->subDay()]);
        $last = Product::factory()->create(['name' => 'Zulu', 'created_at' => now()]);
        foreach (['unknown', 'name desc; DROP TABLE products', ['name']] as $column) {
            $this->get(route('admin.products.index', ['sort' => $column, 'direction' => 'desc']))->assertOk()
                ->assertViewHas('products', fn ($products) => $products->getCollection()->modelKeys() === [$last->id, $first->id])
                ->assertDontSee('aria-sort="descending"', false);
        }
        foreach (['invalid', ['desc'], null] as $direction) {
            $this->get(route('admin.products.index', ['sort' => 'name', 'direction' => $direction]))->assertOk()
                ->assertViewHas('products', fn ($products) => $products->getCollection()->modelKeys() === [$first->id, $last->id]);
        }
    }

    public function test_header_links_cycle_sort_reset_page_and_preserve_other_query_parameters(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $url = route('admin.products.index', ['per_page' => 10, 'page' => 3, 'filter' => ['active']]);
        foreach (['asc', 'desc', null] as $nextDirection) {
            $response = $this->get($url)->assertOk();
            $url = $response->viewData('sorting')->urlFor('name');
            $response->assertSee('href="'.e($url).'"', false)
                ->assertSee('fa-caret-up')->assertSee('fa-caret-down')
                ->assertDontSee('sort=Thao', false);
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
            $this->assertSame($nextDirection, $query['direction'] ?? null);
            $this->assertSame('10', $query['per_page']);
            $this->assertSame(['active'], $query['filter']);
            $this->assertArrayNotHasKey('page', $query);
            if ($nextDirection === null) {
                $this->assertArrayNotHasKey('sort', $query);
            }
        }

        $response = $this->get(route('admin.products.index', ['sort' => 'name', 'direction' => 'desc']))->assertOk();
        parse_str(parse_url($response->viewData('sorting')->urlFor('brand'), PHP_URL_QUERY), $query);
        $this->assertSame(['sort' => 'brand', 'direction' => 'asc'], $query);
        $response = $this->get(route('admin.products.index', ['sort' => 'name', 'direction' => 'desc']))->assertOk();
        $this->assertSame(route('admin.products.index'), $response->viewData('sorting')->urlFor('name'));
    }

    private function assertSortedIds(string $resource, string $viewKey, string $column, array $expected): void
    {
        foreach (['asc' => $expected, 'desc' => array_reverse($expected)] as $direction => $orderedIds) {
            $response = $this->get(route('admin.'.$resource.'.index', [
                'sort' => $column, 'direction' => $direction, 'per_page' => 50,
            ]))->assertOk();
            $actual = $response->viewData($viewKey)->getCollection()->whereIn('id', $expected)->modelKeys();
            $this->assertSame($orderedIds, array_values($actual));
        }
    }
}
