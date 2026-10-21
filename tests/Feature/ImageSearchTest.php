<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImageSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'services.ai.openai_compatible.endpoint' => 'https://internal-ai.example.test/v1',
            'services.ai.openai_compatible.key' => 'test-key',
        ]);

        $men = Category::factory()->create(['name' => 'Nam', 'slug' => 'nam', 'parent_id' => null]);
        $tops = Category::factory()->create(['name' => 'Áo nam', 'slug' => 'nam-ao', 'parent_id' => $men->id]);
        $jackets = Category::factory()->create(['name' => 'Áo khoác', 'slug' => 'ao-khoac-nam', 'parent_id' => $tops->id]);
        $jacket = Product::factory()->for($jackets)->create(['name' => 'Áo khoác jean sọc']);
        ProductVariant::factory()->for($jacket)->create(['color' => 'Xanh nhạt', 'size' => 'M']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeAi(array $overrides = []): void
    {
        $reply = array_merge([
            'is_clothing' => true,
            'keywords' => 'khoác jean',
            'color' => 'Xanh nhạt',
            'category' => 'Nam > Áo nam > Áo khoác',
        ], $overrides);

        Http::fake(['internal-ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode($reply)]]]])]);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('ao-khoac.jpg', 1600, 1200);
    }

    public function test_photo_lands_on_the_catalog_search_for_the_described_garment(): void
    {
        $this->fakeAi();

        $this->post(route('products.image-search'), ['image' => $this->photo()])
            ->assertRedirect(route('products.index', ['q' => 'khoác jean', 'category' => 'ao-khoac-nam', 'color' => ['Xanh nhạt']]));

        Http::assertSent(function (Request $request): bool {
            $system = $request['messages'][0]['content'];
            $image = $request['messages'][1]['content'][0]['image_url']['url'];
            [$width, $height] = getimagesizefromstring(base64_decode(substr($image, strlen('data:image/jpeg;base64,'))));

            return str_contains($system, 'Nam > Áo nam > Áo khoác')
                && str_contains($system, 'Xanh nhạt')
                && max($width, $height) === 768;
        });
    }

    public function test_the_color_is_dropped_when_no_product_has_it(): void
    {
        $this->fakeAi(['color' => 'Đỏ']);

        $this->post(route('products.image-search'), ['image' => $this->photo()])
            ->assertRedirect(route('products.index', ['q' => 'khoác jean', 'category' => 'ao-khoac-nam']));
    }

    public function test_an_unknown_garment_and_category_still_land_on_a_text_search(): void
    {
        $this->fakeAi(['keywords' => 'parka lông vũ', 'category' => 'Không có danh mục này']);

        $this->post(route('products.image-search'), ['image' => $this->photo()])
            ->assertRedirect(route('products.index', ['q' => 'parka lông vũ']));
    }

    public function test_a_photo_without_clothing_returns_with_a_message(): void
    {
        $this->fakeAi(['is_clothing' => false, 'keywords' => '']);

        $this->post(route('products.image-search'), ['image' => $this->photo()])
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('error', 'Không nhận ra sản phẩm trong ảnh, bạn thử ảnh khác nhé.');
    }

    public function test_an_unreadable_or_empty_reply_is_treated_as_not_recognised(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => 'Xin lỗi, tôi không chắc.']]]])
            ->push(['choices' => [['message' => ['content' => '```json'.'
'.'{"is_clothing": true, "keywords": "  "}'.'
'.'```']]]])]);

        foreach ([1, 2] as $attempt) {
            $this->post(route('products.image-search'), ['image' => $this->photo()])
                ->assertRedirect(route('products.index'))
                ->assertSessionHas('error', 'Không nhận ra sản phẩm trong ảnh, bạn thử ảnh khác nhé.');
        }
    }

    public function test_an_ai_outage_returns_with_a_message(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::response('down', 500)]);

        $this->post(route('products.image-search'), ['image' => $this->photo()])
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('error', 'Chưa thể tìm bằng ảnh lúc này. Vui lòng thử lại sau hoặc tìm bằng chữ.');
    }

    public function test_only_images_are_accepted(): void
    {
        Http::fake();

        $this->from(route('products.index'))
            ->post(route('products.image-search'), ['image' => UploadedFile::fake()->create('catalog.pdf', 10, 'application/pdf')])
            ->assertRedirect(route('products.index'))
            ->assertSessionHasErrors(['image' => 'Tệp tải lên phải là ảnh.']);

        Http::assertNothingSent();
    }

    public function test_catalog_search_box_offers_search_by_photo(): void
    {
        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Tìm bằng hình ảnh')
            ->assertSee('action="'.route('products.image-search').'"', false)
            ->assertSee('form="image-search-form"', false);
    }
}
