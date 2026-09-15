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
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPaginationTest extends TestCase
{
    use RefreshDatabase;

    public static function adminLists(): array
    {
        return [
            'brands' => [Brand::class, 'brands', 'brands'],
            'categories' => [Category::class, 'categories', 'categories'],
            'products' => [Product::class, 'products', 'products'],
            'suppliers' => [Supplier::class, 'suppliers', 'suppliers'],
            'discounts' => [Discount::class, 'discounts', 'discounts'],
            'goods receipts' => [GoodsReceipt::class, 'goods-receipts', 'receipts'],
        ];
    }

    #[DataProvider('adminLists')]
    public function test_all_admin_lists_page_through_records_without_overlap(string $model, string $resource, string $viewKey): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $records = $model::factory()->count(21)->create(['created_at' => now()]);
        $seen = [];

        foreach ([1 => 10, 2 => 10, 3 => 1] as $page => $expectedCount) {
            $response = $this->get(route('admin.'.$resource.'.index', ['per_page' => 10, 'page' => $page]));
            $response->assertOk()->assertSee('Mỗi trang')->assertSee('aria-label="Phân trang"', false);

            $paginator = $response->viewData($viewKey);
            $this->assertSame($expectedCount, $paginator->count());
            $this->assertSame(21, $paginator->total());
            $this->assertSame($page, $paginator->currentPage());
            $ids = $paginator->getCollection()->modelKeys();
            $this->assertEmpty(array_intersect($seen, $ids));
            $seen = array_merge($seen, $ids);

            if ($paginator->hasMorePages()) {
                $response->assertSee(e($paginator->nextPageUrl()), false)->assertSee('rel="next"', false);
                $this->assertStringContainsString('per_page=10', $paginator->nextPageUrl());
            } else {
                $response->assertDontSee('rel="next"', false);
            }
        }

        $this->assertEqualsCanonicalizing($records->modelKeys(), $seen);
    }

    public function test_page_size_is_limited_to_the_supported_choices(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach ([10, 20, 50] as $perPage) {
            $this->get(route('admin.products.index', ['per_page' => $perPage]))
                ->assertOk()->assertViewHas('products', fn ($products) => $products->perPage() === $perPage);
        }

        foreach ([0, -1, 1000, 'invalid', ['10']] as $invalid) {
            $this->get(route('admin.products.index', ['per_page' => $invalid]))
                ->assertOk()->assertViewHas('products', fn ($products) => $products->perPage() === 20);
        }
    }

    public function test_empty_and_single_page_lists_show_counts_without_navigation(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.products.index'))->assertOk()->assertSee('0–0')
            ->assertDontSee('aria-label="Phân trang"', false);

        Product::factory()->count(2)->create();
        $this->get(route('admin.products.index'))->assertOk()->assertSee('1–2')
            ->assertDontSee('aria-label="Phân trang"', false);
    }

    public function test_large_page_lists_use_ellipses_in_the_shared_component(): void
    {
        $paginator = new LengthAwarePaginator(range(41, 50), 1000, 10, 5, ['path' => '/admin/san-pham']);
        $paginator->appends(['per_page' => 10]);

        $this->blade('<x-pagination :paginator="$paginator" />', ['paginator' => $paginator])
            ->assertSee('…')->assertSee('Trang 5')->assertSee('Trang 100')
            ->assertDontSee('aria-label="Trang 50"', false);
    }
}
