<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SizeChart;
use App\Models\User;
use App\Services\Catalog\SizeChartResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SizeChartTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    private Category $men;

    private Category $menTops;

    private Category $tees;

    protected function setUp(): void
    {
        parent::setUp();
        $this->brand = Brand::factory()->create();
        $this->men = Category::factory()->create(['name' => 'Nam']);
        $this->menTops = Category::factory()->childOf($this->men)->create(['name' => 'Áo nam']);
        $this->tees = Category::factory()->childOf($this->menTops)->create(['name' => 'Áo thun']);
    }

    private function chart(string $name, ?Brand $brand = null, ?Category $category = null): SizeChart
    {
        return SizeChart::factory()->create(['name' => $name, 'brand_id' => $brand?->id, 'category_id' => $category?->id]);
    }

    private function tee(array $attributes = []): Product
    {
        return Product::factory()->create(['brand_id' => $this->brand->id, 'category_id' => $this->tees->id] + $attributes);
    }

    public function test_the_brand_beats_the_category_and_a_nearer_category_beats_a_farther_one(): void
    {
        $resolver = app(SizeChartResolver::class);
        $product = $this->tee();
        $this->assertNull($resolver->forProduct($product));

        $this->chart('Chung');
        $this->assertSame('Chung', $resolver->forProduct($product)->name);

        $this->chart('Mọi hãng · Áo nam', category: $this->menTops);
        $this->assertSame('Mọi hãng · Áo nam', $resolver->forProduct($product)->name);

        $this->chart('Hãng · Nam', $this->brand, $this->men);
        $this->assertSame('Hãng · Nam', $resolver->forProduct($product)->name);

        $this->chart('Hãng · Áo nam', $this->brand, $this->menTops);
        $this->chart('Hãng khác · Áo thun', Brand::factory()->create(), $this->tees);
        $this->assertSame('Hãng · Áo nam', $resolver->forProduct($product)->name);
    }

    public function test_a_chart_picked_on_the_product_wins_over_the_automatic_one(): void
    {
        $this->chart('Hãng · Áo nam', $this->brand, $this->menTops);
        $picked = $this->chart('Chọn riêng');

        $this->assertSame('Chọn riêng', app(SizeChartResolver::class)->forProduct($this->tee(['size_chart_id' => $picked->id]))->name);
    }

    public function test_the_product_page_shows_the_chart_with_the_suggestion_only_when_there_is_one(): void
    {
        $product = $this->tee(['status' => 'active']);
        ProductVariant::factory()->for($product)->create(['size' => 'M', 'is_active' => true]);

        $this->get(route('products.show', $product))->assertOk()->assertDontSee('size-guide-dialog', false);

        $this->chart('Hãng · Áo nam', $this->brand, $this->menTops)->update(['note' => 'Chọn size lớn hơn nếu phân vân.']);
        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('size-guide-dialog', false)
            ->assertSee('Gợi ý size cho bạn')
            ->assertSeeInOrder(['Vòng ngực (cm)', 'M', '160–168', '55–62', '90-94'])
            ->assertSee('Chọn size lớn hơn nếu phân vân.');
    }

    public function test_a_chart_without_height_or_weight_shows_no_suggestion(): void
    {
        $product = $this->tee(['status' => 'active']);
        ProductVariant::factory()->for($product)->create(['size' => 'M', 'is_active' => true]);
        SizeChart::factory()->create(['rows' => [['size' => 'M', 'height_min' => null, 'height_max' => null, 'weight_min' => null, 'weight_max' => null, 'values' => ['90']]]]);

        $this->get(route('products.show', $product))->assertSee('size-guide-dialog', false)->assertDontSee('Gợi ý size cho bạn');
    }

    public function test_staff_can_create_a_chart_with_blank_columns_dropped_and_rows_in_size_order(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.size-charts.store'), [
            'name' => 'Hãng · Áo nam',
            'brand_id' => $this->brand->id,
            'category_id' => $this->menTops->id,
            'columns' => ['Vòng ngực (cm)', '', 'Dài áo (cm)'],
            'rows' => [
                ['size' => 'L', 'height_min' => '168', 'height_max' => '174', 'weight_min' => '', 'weight_max' => '', 'values' => ['94-98', 'bỏ', '70']],
                ['size' => 'M', 'height_min' => '160', 'height_max' => '168', 'values' => ['90-94', '', '68']],
            ],
        ])->assertRedirect(route('admin.size-charts.index'))->assertSessionHasNoErrors();

        $chart = SizeChart::query()->sole();
        $this->assertSame(['Vòng ngực (cm)', 'Dài áo (cm)'], $chart->columns);
        $this->assertSame(['M', 'L'], array_column($chart->rows, 'size'));
        $this->assertSame(['94-98', '70'], $chart->rows[1]['values']);
        $this->assertNull($chart->rows[1]['weight_min']);
        $this->assertTrue(AuditLog::query()->where('action', 'size_chart.created')->exists());
    }

    public function test_bad_rows_and_a_second_chart_for_the_same_brand_and_category_are_rejected(): void
    {
        $this->chart('Đã có', $this->brand, $this->menTops);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.size-charts.store'), [
            'name' => 'Trùng', 'brand_id' => $this->brand->id, 'category_id' => $this->menTops->id,
            'rows' => [['size' => 'M'], ['size' => 'M'], ['size' => 'L', 'height_min' => 180, 'height_max' => 170], ['size' => 'XXXL']],
        ])->assertSessionHasErrors(['category_id', 'rows.1.size', 'rows.2.height_max', 'rows.3.size']);

        $this->actingAs($admin)->post(route('admin.size-charts.store'), ['name' => 'Rỗng', 'rows' => []])->assertSessionHasErrors('rows');
        $this->assertSame(1, SizeChart::query()->count());
    }

    public function test_editing_keeps_the_chart_and_deleting_sends_its_products_back_to_automatic(): void
    {
        $admin = User::factory()->admin()->create();
        $chart = $this->chart('Cũ', $this->brand, $this->menTops);
        $product = $this->tee(['size_chart_id' => $chart->id]);

        $this->actingAs($admin)->get(route('admin.size-charts.edit', $chart))->assertOk()->assertSee('Cũ');
        $this->actingAs($admin)->put(route('admin.size-charts.update', $chart), [
            'name' => 'Mới', 'brand_id' => $this->brand->id, 'category_id' => $this->menTops->id, 'rows' => [['size' => 'S']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Mới', $chart->fresh()->name);

        $this->actingAs($admin)->delete(route('admin.size-charts.destroy', $chart))->assertRedirect(route('admin.size-charts.index'));
        $this->assertNull($product->fresh()->size_chart_id);
    }

    public function test_the_list_and_form_need_products_manage(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.size-charts.index'))->assertForbidden();

        $this->chart('Hãng · Áo nam', $this->brand, $this->menTops);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.size-charts.index'))
            ->assertOk()->assertSee('Hãng · Áo nam')->assertSee($this->brand->name.' · Áo nam');
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.size-charts.create'))
            ->assertOk()->assertSee('Nam › Áo nam › Áo thun');
    }

    public function test_the_product_form_saves_a_picked_chart_and_shows_the_automatic_one(): void
    {
        $admin = User::factory()->admin()->create();
        $auto = $this->chart('Hãng · Áo nam', $this->brand, $this->menTops);
        $product = $this->tee();

        $this->actingAs($admin)->get(route('admin.products.edit', $product))->assertOk()->assertSee('Đang dùng: Hãng · Áo nam');

        $picked = $this->chart('Chọn riêng');
        $product->update(['size_chart_id' => $picked->id]);
        $this->actingAs($admin)->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('<option value="'.$picked->id.'" selected', false)
            ->assertDontSee('Đang dùng: '.$auto->name);
    }
}
