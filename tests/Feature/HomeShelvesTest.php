<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeShelvesTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        $product = Product::factory()->create($attributes);
        ProductVariant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 5]);

        return $product;
    }

    public function test_the_cover_leads_with_a_menswear_product(): void
    {
        $men = Category::factory()->create(['slug' => 'nam', 'parent_id' => null]);
        $menShirts = Category::factory()->childOf(Category::factory()->childOf($men)->create())->create();

        $menswear = $this->product(['name' => 'Áo khoác nam gió', 'is_featured' => true, 'category_id' => $menShirts->id]);
        $this->product(['name' => 'Váy nữ mới nhất', 'is_featured' => true]);

        $this->get(route('home'))->assertOk()
            ->assertViewHas('cover', fn ($cover) => $cover->first()->is($menswear))
            ->assertSeeInOrder(['class="cover-lead"', 'Áo khoác nam gió'], false);
    }

    public function test_each_shelf_shows_four_products_and_links_to_the_next_four(): void
    {
        foreach (range(1, 6) as $index) {
            $this->product(['name' => "Mẫu mới {$index}"]);
        }

        $this->get(route('home'))->assertOk()
            ->assertViewHas('shelves', fn ($shelves) => count($shelves['moi-ve']->items()) === 4)
            ->assertSee(str_replace('/', '\/', route('home.shelf', ['shelf' => 'moi-ve', 'page' => 2])), false);

        $this->getJson(route('home.shelf', ['shelf' => 'moi-ve', 'page' => 2]))
            ->assertOk()
            ->assertJsonPath('next', null)
            ->assertJson(fn ($json) => $json->where('html', fn ($html) => str_contains($html, 'Mẫu mới 2') && str_contains($html, 'Mẫu mới 1')
                && ! str_contains($html, 'Mẫu mới 3'))->etc());
    }

    public function test_the_featured_shelf_skips_the_products_on_the_cover(): void
    {
        $onCover = $this->product(['name' => 'Đang trên bìa', 'is_featured' => true]);
        $this->product(['name' => 'Nổi bật khác', 'is_featured' => true]);

        $html = $this->getJson(route('home.shelf', ['shelf' => 'noi-bat', 'except' => (string) $onCover->id]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Nổi bật khác', $html);
        $this->assertStringNotContainsString('Đang trên bìa', $html);
    }

    public function test_the_sale_shelf_holds_only_discounted_products(): void
    {
        $onSale = $this->product(['name' => 'Áo đang giảm']);
        Discount::factory()->for($onSale->variants->first())->create(['discount_type' => 'percent', 'discount_value' => 10]);
        $this->product(['name' => 'Áo giá gốc']);

        $html = $this->getJson(route('home.shelf', ['shelf' => 'giam-gia']))->assertOk()->json('html');

        $this->assertStringContainsString('Áo đang giảm', $html);
        $this->assertStringNotContainsString('Áo giá gốc', $html);
    }

    public function test_an_unknown_shelf_is_not_found(): void
    {
        $this->get('/ke-san-pham/khong-co')->assertNotFound();
    }
}
