<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProductAiExcelImportTest extends TestCase
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
        Category::factory()->create(['name' => 'Áo thun']);
        Brand::factory()->create(['name' => 'Mộc Daily']);
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    private function xlsx(array $rows, string $title = 'Bảng giá'): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle($title);
        foreach ($rows as $r => $row) {
            foreach ($row as $c => $value) {
                if ($value !== null) {
                    $sheet->getCell([$c + 1, $r + 1])->setValueExplicit($value, is_string($value) ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
                }
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'ai-excel-test-');
        (new Xlsx($book))->save($path);
        $content = file_get_contents($path);
        unlink($path);
        $book->disconnectWorksheets();

        return UploadedFile::fake()->createWithContent('san-pham.xlsx', $content);
    }

    /**
     * @param  array<string, string|null>  $columns
     */
    private function fakeMapping(array $columns, int $headerRow = 1, string $sheet = 'Bảng giá'): void
    {
        $columns += array_fill_keys(['name', 'category', 'brand', 'description', 'price', 'size', 'color', 'sku', 'variant_price', 'status'], null);
        $reply = ['sheet' => $sheet, 'header_row' => $headerRow, 'first_data_row' => $headerRow + 1, 'columns' => $columns, 'note' => 'ok'];
        Http::fake(['internal-ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode($reply)]]]])]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function read(UploadedFile $file, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->admin())->postJson(route('admin.products.ai-excel.read'), ['file' => $file]);
    }

    /** One row per variant, with a banner above the header and "đ"/"k" prices. */
    private function variantRows(): array
    {
        return [
            ['BẢNG GIÁ THÁNG 10', null, null, null, null, null, null],
            [],
            ['Tên hàng', 'Nhóm', 'Hãng', 'Size', 'Màu', 'Mã', 'Đơn giá'],
            ['Áo thun cổ tròn', 'Áo thun', 'Mộc Daily', 'M', 'Trắng', 'ATCT-M-TR', '350.000đ'],
            [null, null, null, 'L', 'Trắng', 'ATCT-L-TR', '350.000đ'],
            [null, null, null, 'M', 'Đen', null, '350.000đ'],
            ['Tổng', null, null, null, null, null, null],
        ];
    }

    private function variantColumns(): array
    {
        return ['name' => 'A', 'category' => 'B', 'brand' => 'C', 'size' => 'D', 'color' => 'E', 'sku' => 'F', 'price' => 'G'];
    }

    public function test_staff_without_product_permission_cannot_use_the_excel_assistant(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.products.ai-excel.read'), ['file' => $this->xlsx([['a']])])
            ->assertForbidden();
    }

    public function test_only_xlsx_files_are_accepted(): void
    {
        $this->read(UploadedFile::fake()->createWithContent('san-pham.csv', "a,b\n1,2"))->assertJsonValidationErrors('file');
    }

    public function test_reading_a_file_returns_products_with_variants_and_saves_nothing(): void
    {
        $this->fakeMapping($this->variantColumns(), headerRow: 3);

        $response = $this->read($this->xlsx($this->variantRows()))->assertOk();

        $response->assertJsonPath('data.product_count', 1)
            ->assertJsonPath('data.variant_count', 3)
            ->assertJsonPath('data.products.0.name', 'Áo thun cổ tròn')
            ->assertJsonPath('data.products.0.category', 'Áo thun')
            ->assertJsonPath('data.products.0.brand', 'Mộc Daily')
            ->assertJsonPath('data.products.0.price', 350000)
            ->assertJsonPath('data.products.0.variants.0.size', 'M')
            ->assertJsonPath('data.products.0.variants.0.color', 'Trắng')
            ->assertJsonPath('data.products.0.variants.0.sku', 'ATCT-M-TR')
            ->assertJsonPath('data.columns.name.header', 'Tên hàng')
            ->assertJsonPath('data.columns.price.column', 'G')
            ->assertJsonPath('data.skipped_count', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_missing_skus_are_generated_and_never_collide_with_existing_ones(): void
    {
        ProductVariant::factory()->create(['sku' => 'ATCT-L-TR']);
        $this->fakeMapping($this->variantColumns(), headerRow: 3);

        $variants = collect($this->read($this->xlsx($this->variantRows()))->assertOk()->json('data.products.0.variants'));

        $this->assertContains('ATCT-M-TR', $variants->pluck('sku')->all());
        $this->assertContains('ATCT-L-TR-2', $variants->pluck('sku')->all(), 'a SKU already used elsewhere is suffixed, never reused');
        $this->assertContains('AO-THUN-CO-TRON-M-DEN', $variants->pluck('sku')->all(), 'a missing SKU is generated');
    }

    public function test_the_model_only_sees_a_snapshot_and_is_never_asked_to_copy_data(): void
    {
        $this->fakeMapping($this->variantColumns(), headerRow: 3);

        $this->read($this->xlsx($this->variantRows()))->assertOk();

        Http::assertSent(function ($request) {
            $text = json_encode($request->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return str_contains($text, 'Sheet \"Bảng giá\"') && str_contains($text, 'Tên hàng') && str_contains($text, 'Dòng | A | B | C');
        });
    }

    public function test_size_and_color_lists_expand_and_prices_are_normalized(): void
    {
        $this->fakeMapping(['name' => 'A', 'category' => 'B', 'brand' => 'C', 'price' => 'D', 'size' => 'E', 'color' => 'F']);

        $response = $this->read($this->xlsx([
            ['Sản phẩm', 'Loại', 'Hãng', 'Giá', 'Size', 'Màu'],
            ['Áo thun basic', 'Ao Thun', 'moc daily', '1,2tr', 'S, M, XXL', 'Trắng / Đen'],
        ]))->assertOk();

        $response->assertJsonPath('data.product_count', 1)
            ->assertJsonPath('data.variant_count', 6)
            ->assertJsonPath('data.products.0.price', 1200000)
            ->assertJsonPath('data.products.0.category', 'Áo thun')
            ->assertJsonPath('data.products.0.brand', 'Mộc Daily');
        $this->assertSame(['S', 'S', 'M', 'M', '2XL', '2XL'], collect($response->json('data.products.0.variants'))->pluck('size')->all());
    }

    public function test_status_is_only_reported_when_the_file_has_one(): void
    {
        $this->fakeMapping(['name' => 'A', 'category' => 'B', 'brand' => 'C', 'price' => 'D', 'status' => 'E']);

        $response = $this->read($this->xlsx([
            ['Tên', 'Loại', 'Hãng', 'Giá', 'Tình trạng'],
            ['Áo đang bán', 'Áo thun', 'Mộc Daily', 100000, 'Đang kinh doanh'],
            ['Áo ngừng bán', 'Áo thun', 'Mộc Daily', 100000, 'Ngừng bán'],
            ['Áo không ghi', 'Áo thun', 'Mộc Daily', 100000, null],
        ]))->assertOk();

        $this->assertSame(['active', 'archived', null], collect($response->json('data.products'))->pluck('status')->all());
    }

    public function test_products_that_cannot_be_filled_are_reported_with_their_reason(): void
    {
        Product::factory()->create(['name' => 'Áo đã có']);
        $this->fakeMapping(['name' => 'A', 'category' => 'B', 'brand' => 'C', 'price' => 'D', 'size' => 'E', 'color' => 'F']);

        $response = $this->read($this->xlsx([
            ['Tên', 'Loại', 'Hãng', 'Giá', 'Size', 'Màu'],
            ['Áo hợp lệ', 'Áo thun', 'Mộc Daily', 350000, 'M', 'Đen'],
            ['Áo sai danh mục', 'Váy lạ', 'Mộc Daily', 350000, 'M', 'Đen'],
            ['Áo đã có', 'Áo thun', 'Mộc Daily', 350000, 'M', 'Đen'],
            ['Áo thiếu giá', 'Áo thun', 'Mộc Daily', null, 'M', 'Đen'],
            ['Áo thiếu hãng', 'Áo thun', null, 350000, 'M', 'Đen'],
        ]))->assertOk();

        $response->assertJsonPath('data.product_count', 1)->assertJsonPath('data.skipped_count', 4);
        $reasons = collect($response->json('data.skipped'))->pluck('reason', 'name');
        $this->assertStringContainsString('Váy lạ', $reasons['Áo sai danh mục']);
        $this->assertStringContainsString('trùng tên', $reasons['Áo đã có']);
        $this->assertSame('Thiếu giá bán.', $reasons['Áo thiếu giá']);
        $this->assertSame('Thiếu thương hiệu.', $reasons['Áo thiếu hãng']);
    }

    public function test_products_whose_category_name_belongs_to_several_records_are_skipped(): void
    {
        Category::factory()->create(['name' => 'Áo thun']);
        $this->fakeMapping(['name' => 'A', 'category' => 'B', 'brand' => 'C', 'price' => 'D']);

        $response = $this->read($this->xlsx([
            ['Tên', 'Loại', 'Hãng', 'Giá'],
            ['Áo mơ hồ', 'Áo thun', 'Mộc Daily', 100000],
        ]))->assertOk();

        $response->assertJsonPath('data.product_count', 0);
        $this->assertStringContainsString('nhiều danh mục cùng tên', $response->json('data.skipped.0.reason'));
    }

    public function test_invalid_sizes_and_duplicate_variants_are_dropped_with_warnings(): void
    {
        $this->fakeMapping(['name' => 'A', 'category' => 'B', 'brand' => 'C', 'price' => 'D', 'size' => 'E', 'color' => 'F']);

        $response = $this->read($this->xlsx([
            ['Tên', 'Loại', 'Hãng', 'Giá', 'Size', 'Màu'],
            ['Áo thun lạ', 'Áo thun', 'Mộc Daily', 100000, 'FREE', 'Đen'],
            [null, null, null, null, 'M', 'Đen'],
            [null, null, null, null, 'M', 'Đen'],
        ]))->assertOk();

        $response->assertJsonPath('data.variant_count', 1)->assertJsonPath('data.warning_count', 2);
        $warnings = implode(' ', $response->json('data.warnings'));
        $this->assertStringContainsString('"FREE" không hợp lệ', $warnings);
        $this->assertStringContainsString('bị lặp', $warnings);
    }

    public function test_an_unusable_model_answer_is_reported_as_a_bad_gateway(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => 'không phải JSON']]]])]);

        $this->read($this->xlsx($this->variantRows()))->assertStatus(502);
    }

    public function test_a_model_that_returns_no_name_column_is_rejected(): void
    {
        $this->fakeMapping(['category' => 'B'], headerRow: 3);

        $this->read($this->xlsx($this->variantRows()))->assertStatus(502);
    }

    public function test_an_unreachable_model_is_reported_as_unavailable(): void
    {
        Http::fake(['internal-ai.example.test/*' => Http::failedConnection()]);

        $this->read($this->xlsx($this->variantRows()))->assertStatus(503);
    }

    public function test_an_empty_workbook_is_rejected_before_the_model_is_called(): void
    {
        Http::fake();

        $this->read($this->xlsx([[]]))->assertJsonValidationErrors('file');

        Http::assertNothingSent();
    }
}
