<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\CollectionEngine;
use RuntimeException;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_needs_every_word_somewhere_in_name_brand_category_or_color(): void
    {
        $brand = Brand::factory()->create(['name' => 'Gió Biển']);
        $category = Category::factory()->create(['name' => 'Áo sơ mi']);
        $match = Product::factory()->for($brand)->for($category)->create(['name' => 'Linen cổ đức']);
        ProductVariant::factory()->for($match)->create(['color' => 'Trắng']);
        $otherColor = Product::factory()->for($category)->create(['name' => 'Oxford tay dài']);
        ProductVariant::factory()->for($otherColor)->create(['color' => 'Xanh']);
        $archived = Product::factory()->for($brand)->for($category)->create(['name' => 'Linen cũ', 'status' => 'archived']);
        ProductVariant::factory()->for($archived)->create(['color' => 'Trắng']);

        $this->get(route('products.index', ['q' => 'sơ mi Trắng']))
            ->assertOk()
            ->assertSee('Kết quả cho “sơ mi Trắng”', false)
            ->assertSee('Linen cổ đức')
            ->assertDontSee('Oxford tay dài')
            ->assertDontSee('Linen cũ');

        $this->get(route('products.index', ['q' => 'Gió Biển']))->assertSee('Linen cổ đức')->assertDontSee('Oxford tay dài');
        $this->get(route('products.index', ['q' => 'không có gì']))->assertSee('Không tìm thấy sản phẩm nào cho “không có gì”', false);
    }

    public function test_size_color_and_in_stock_must_hold_on_the_same_variant(): void
    {
        $blackM = Product::factory()->create(['name' => 'Có đen M còn hàng']);
        ProductVariant::factory()->for($blackM)->create(['size' => 'M', 'color' => 'Đen', 'stock_quantity' => 3]);
        $split = Product::factory()->create(['name' => 'Đen là L, M là trắng']);
        ProductVariant::factory()->for($split)->create(['size' => 'L', 'color' => 'Đen', 'stock_quantity' => 3]);
        ProductVariant::factory()->for($split)->create(['size' => 'M', 'color' => 'Trắng', 'stock_quantity' => 3]);
        $soldOut = Product::factory()->create(['name' => 'Đen M hết hàng']);
        ProductVariant::factory()->for($soldOut)->outOfStock()->create(['size' => 'M', 'color' => 'Đen']);

        $this->get(route('products.index', ['size' => ['M'], 'color' => ['Đen']]))
            ->assertSee('Có đen M còn hàng')
            ->assertSee('Đen M hết hàng')
            ->assertDontSee('Đen là L, M là trắng');

        $this->get(route('products.index', ['size' => ['M'], 'color' => ['Đen'], 'in_stock' => 1]))
            ->assertSee('Có đen M còn hàng')
            ->assertDontSee('Đen M hết hàng');

        $this->get(route('products.index', ['size' => ['M', 'L'], 'color' => 'Đen']))
            ->assertSee('Có đen M còn hàng')
            ->assertSee('Đen là L, M là trắng');
    }

    public function test_price_filter_and_sort_use_the_discounted_price_shown_on_the_card(): void
    {
        $onSale = Product::factory()->create(['name' => 'Đang giảm', 'base_price' => 600000]);
        $saleVariant = ProductVariant::factory()->for($onSale)->create(['price' => 600000]);
        Discount::factory()->for($saleVariant)->create(['discount_type' => 'percent', 'discount_value' => 50]);
        $cheap = Product::factory()->create(['name' => 'Rẻ nhất', 'base_price' => 200000]);
        ProductVariant::factory()->for($cheap)->create(['price' => 200000]);
        $pricey = Product::factory()->create(['name' => 'Đắt nhất', 'base_price' => 450000]);
        ProductVariant::factory()->for($pricey)->create(['price' => 450000]);

        $this->get(route('products.index', ['price_min' => 300000, 'price_max' => 500000]))
            ->assertSee('Đang giảm')
            ->assertSee('Đắt nhất')
            ->assertDontSee('Rẻ nhất');

        $this->get(route('products.index', ['sort' => 'gia-tang']))
            ->assertSeeInOrder(['Rẻ nhất', 'Đang giảm', 'Đắt nhất']);
        $this->get(route('products.index', ['sort' => 'gia-giam']))
            ->assertSeeInOrder(['Đắt nhất', 'Đang giảm', 'Rẻ nhất']);
    }

    public function test_price_sorted_results_are_paged(): void
    {
        foreach (range(1, 13) as $index) {
            $product = Product::factory()->create(['name' => sprintf('Mẫu số %02d', $index)]);
            ProductVariant::factory()->for($product)->create(['price' => $index * 10000]);
        }

        $this->get(route('products.index', ['sort' => 'gia-tang']))
            ->assertSee('Mẫu số 12')
            ->assertDontSee('Mẫu số 13')
            ->assertSee('13 sản phẩm');
        $this->get(route('products.index', ['sort' => 'gia-tang', 'page' => 2]))
            ->assertSee('Mẫu số 13')
            ->assertDontSee('Mẫu số 12');
    }

    public function test_meilisearch_hits_come_back_in_relevance_order_by_default(): void
    {
        $first = Product::factory()->create(['name' => 'Kết quả hạng nhì']);
        $second = Product::factory()->create(['name' => 'Kết quả hạng nhất']);
        Product::factory()->create(['name' => 'Không khớp']);
        $engine = $this->fakeMeilisearch(rankedIds: [$second->id, $first->id]);

        $this->get(route('products.index', ['q' => 'hang']))
            ->assertOk()
            ->assertSeeInOrder(['Kết quả hạng nhất', 'Kết quả hạng nhì'])
            ->assertDontSee('Không khớp')
            ->assertSee('Phù hợp nhất');

        $this->assertSame(['hang'], $engine->queries);
    }

    public function test_search_falls_back_to_the_database_when_meilisearch_fails(): void
    {
        Product::factory()->create(['name' => 'Áo khoác gió']);
        $this->fakeMeilisearch(fails: true);

        $this->get(route('products.index', ['q' => 'khoác']))
            ->assertOk()
            ->assertSee('Áo khoác gió');
    }

    public function test_new_products_are_indexed_and_archived_ones_removed(): void
    {
        $engine = $this->fakeMeilisearch();

        $product = Product::factory()->create();
        $this->assertContains($product->id, $engine->indexedIds);

        $product->update(['status' => 'archived']);
        $this->assertSame([$product->id], $engine->removedIds);
    }

    public function test_variant_and_brand_changes_reindex_the_product(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['color' => 'Đen']);
        $engine = $this->fakeMeilisearch();

        $variant->update(['stock_quantity' => 1]);
        $this->assertSame([], $engine->indexedIds);

        $variant->update(['color' => 'Trắng']);
        $this->assertSame([$product->id], $engine->indexedIds);

        $product->brand->update(['name' => 'Tên mới']);
        $this->assertSame([$product->id, $product->id], $engine->indexedIds);
    }

    public function test_search_document_carries_brand_category_path_and_active_colors(): void
    {
        $parent = Category::factory()->create(['name' => 'Nữ']);
        $child = Category::factory()->create(['name' => 'Áo sơ mi', 'parent_id' => $parent->id]);
        $product = Product::factory()->for($child)->for(Brand::factory()->create(['name' => 'Gió Biển']))->create();
        ProductVariant::factory()->for($product)->create(['color' => 'Đen', 'sku' => 'SM-01']);
        ProductVariant::factory()->for($product)->inactive()->create(['color' => 'Đỏ']);

        $document = $product->fresh()->toSearchableArray();

        $this->assertSame('Gió Biển', $document['brand']);
        $this->assertSame('Nữ Áo sơ mi', $document['category']);
        $this->assertSame(['Đen'], $document['colors']);
        $this->assertSame(['SM-01'], $document['skus']);
        $this->assertFalse(Product::factory()->make(['status' => 'archived'])->shouldBeSearchable());
    }

    public function test_filter_chips_list_existing_sizes_in_order_and_keep_other_filters(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create(['size' => 'Free Size', 'color' => 'Be']);
        ProductVariant::factory()->for($product)->create(['size' => 'M', 'color' => 'Be']);
        ProductVariant::factory()->for($product)->create(['size' => 'S', 'color' => 'Be']);

        $this->get(route('products.index', ['q' => 'áo', 'size' => ['M']]))
            ->assertSeeInOrder(['>S<', '>M<', '>Free Size<'], false)
            ->assertSee(e(route('products.index', ['q' => 'áo', 'size' => ['M', 'S']])), false)
            ->assertSee(e(route('products.index', ['q' => 'áo'])), false)
            ->assertSee('Xóa bộ lọc');
    }

    public function test_text_searches_are_rate_limited_but_browsing_is_not(): void
    {
        config(['scout.catalog_searches_per_minute' => 2]);

        $this->get(route('products.index', ['q' => 'áo']))->assertOk();
        $this->get(route('products.index', ['q' => 'quần']))->assertOk();
        $this->get(route('products.index', ['q' => 'váy']))->assertTooManyRequests();
        $this->get(route('products.index', ['size' => ['M']]))->assertOk();
    }

    /**
     * Point Scout at an in-memory engine standing in for Meilisearch:
     * search returns `$rankedIds` (or throws), and every index update and
     * removal is recorded.
     *
     * @param  list<int>  $rankedIds
     */
    private function fakeMeilisearch(array $rankedIds = [], bool $fails = false): object
    {
        $engine = new class($rankedIds, $fails) extends CollectionEngine
        {
            /** @var list<string> */
            public array $queries = [];

            /** @var list<int> */
            public array $indexedIds = [];

            /** @var list<int> */
            public array $removedIds = [];

            /**
             * @param  list<int>  $rankedIds
             */
            public function __construct(private array $rankedIds, private bool $fails) {}

            public function keys(Builder $builder): Collection
            {
                if ($this->fails) {
                    throw new RuntimeException('Meilisearch is down');
                }
                $this->queries[] = $builder->query;

                return collect($this->rankedIds);
            }

            public function update($models): void
            {
                foreach ($models as $model) {
                    $this->indexedIds[] = $model->id;
                }
            }

            public function delete($models): void
            {
                foreach ($models as $model) {
                    $this->removedIds[] = $model->id;
                }
            }
        };

        config(['scout.driver' => 'meilisearch']);
        $this->app->make(EngineManager::class)->extend('meilisearch', fn () => $engine);

        return $engine;
    }
}
