<?php

namespace Tests\Feature;

use App\Actions\AdminExcel;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminExcelTest extends TestCase
{
    use RefreshDatabase;

    public static function resources(): array
    {
        return array_map(fn ($resource) => [$resource], array_combine(
            ['brands', 'categories', 'suppliers', 'products', 'discounts', 'goods-receipts'],
            ['brands', 'categories', 'suppliers', 'products', 'discounts', 'goods-receipts']
        ));
    }

    #[DataProvider('resources')]
    public function test_exports_real_excel_with_headers_and_related_rows(string $resource): void
    {
        $model = $this->record($resource);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.excel.export', $resource));

        $response->assertDownload();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $book = $this->readBytes($response->streamedContent());
        $this->assertSame($model->id, $book->getSheetByName('Dữ liệu')->getCell('A2')->getValue());
        $this->assertNotNull($book->getSheetByName('Hướng dẫn'));
        if ($resource === 'products') {
            $this->assertSame('Tên danh mục', $book->getSheetByName('Dữ liệu')->getCell('D1')->getValue());
            $this->assertSame('Tên thương hiệu', $book->getSheetByName('Dữ liệu')->getCell('E1')->getValue());
            $this->assertSame($model->category->name, $book->getSheetByName('Dữ liệu')->getCell('D2')->getValue());
            $this->assertSame($model->brand->name, $book->getSheetByName('Dữ liệu')->getCell('E2')->getValue());
            $this->assertSame('TEST-SKU', $book->getSheetByName('Biến thể')->getCell('E2')->getValue());
            $this->assertSame(7, $book->getSheetByName('Biến thể')->getCell('I2')->getValue());
        } elseif ($resource === 'goods-receipts') {
            $this->assertSame('TEST-SKU', $book->getSheetByName('Dòng hàng')->getCell('B2')->getValue());
            $this->assertSame(3, $book->getSheetByName('Dòng hàng')->getCell('C2')->getValue());
        } elseif ($resource === 'suppliers') {
            $this->assertSame('0901234567', $book->getSheetByName('Dữ liệu')->getCell('C2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $book->getSheetByName('Dữ liệu')->getCell('C2')->getDataType());
        }
        $book->disconnectWorksheets();
    }

    #[DataProvider('resources')]
    public function test_templates_have_empty_data_and_can_create_records(string $resource): void
    {
        $existing = $this->record($resource);
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route('admin.excel.template', $resource));
        $book = $this->readBytes($response->streamedContent());
        $this->assertSame(1, $book->getSheetByName('Dữ liệu')->getHighestDataRow());
        $source = $this->actingAs($admin)->get(route('admin.excel.export', $resource));
        $export = $this->readBytes($source->streamedContent());
        $book->getSheetByName('Dữ liệu')->fromArray($export->getSheetByName('Dữ liệu')->toArray(null, false, false)[1], null, 'A2', true);
        $book->getSheetByName('Dữ liệu')->setCellValue('A2', null);
        if (in_array($resource, ['brands', 'categories', 'suppliers'], true)) {
            $book->getSheetByName('Dữ liệu')->setCellValue('B2', 'Tên mới tiếng Việt');
        }
        if ($resource === 'suppliers') {
            $book->getSheetByName('Dữ liệu')->setCellValue('F2', null);
        }
        if ($resource === 'products') {
            $this->assertSame('Tên danh mục', $book->getSheetByName('Dữ liệu')->getCell('D1')->getValue());
            $this->assertSame('Tên thương hiệu', $book->getSheetByName('Dữ liệu')->getCell('E1')->getValue());
            $book->getSheetByName('Dữ liệu')->setCellValue('C2', 'Sản phẩm mới');
            $book->getSheetByName('Biến thể')->fromArray([$existing->id, null, 'L', 'Blue', 'NEW-SKU', 100000, 5, 1, 9999], null, 'A2');
        } elseif ($resource === 'goods-receipts') {
            $book->getSheetByName('Dòng hàng')->fromArray([$existing->id, 'TEST-SKU', 4, 25000], null, 'A2');
        }

        $response = $this->actingAs($admin)->post(route('admin.excel.import', $resource), ['file' => $this->upload($book)]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.'.$resource.'.index'));
        $class = $existing::class;
        $this->assertSame(2, $class::query()->count());
        if ($resource === 'products') {
            $this->assertDatabaseHas('products', ['name' => 'Sản phẩm mới', 'category_id' => $existing->category_id, 'brand_id' => $existing->brand_id]);
            $this->assertDatabaseHas('product_variants', ['sku' => 'NEW-SKU', 'stock_quantity' => 0]);
        } elseif ($resource === 'goods-receipts') {
            $this->assertDatabaseHas('goods_receipts', ['status' => 'draft', 'total_cost' => 100000]);
            $this->assertSame(7, ProductVariant::query()->where('sku', 'TEST-SKU')->value('stock_quantity'));
        }
        $export->disconnectWorksheets();
    }

    #[DataProvider('resources')]
    public function test_exported_records_can_be_updated_by_id_without_creating_duplicates(string $resource): void
    {
        $model = $this->record($resource);
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route('admin.excel.export', $resource));
        $book = $this->readBytes($response->streamedContent());
        $main = $book->getSheetByName('Dữ liệu');
        if ($resource === 'discounts') {
            $main->setCellValue('D2', 15);
        } elseif ($resource === 'goods-receipts') {
            $main->setCellValue('D2', 'Ghi chú mới');
            $book->getSheetByName('Dòng hàng')->setCellValue('C2', 5);
        } elseif ($resource === 'products') {
            $main->setCellValue('C2', 'Sản phẩm đổi tên');
            $book->getSheetByName('Biến thể')->setCellValue('I2', 9999);
        } else {
            $main->setCellValue('B2', 'Đổi tên từ Excel');
        }

        $response = $this->actingAs($admin)->post(route('admin.excel.import', $resource), ['file' => $this->upload($book)]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.'.$resource.'.index'));
        $this->assertSame(1, $model::query()->count());
        $model->refresh();
        if ($resource === 'discounts') {
            $this->assertSame(15, (int) $model->discount_value);
        } elseif ($resource === 'goods-receipts') {
            $this->assertSame('Ghi chú mới', $model->notes);
            $this->assertSame(125000, (int) $model->total_cost);
            $this->assertSame(5, $model->items()->first()->quantity);
        } elseif ($resource === 'products') {
            $this->assertSame('Sản phẩm đổi tên', $model->name);
            $this->assertSame(7, $model->variants()->first()->stock_quantity);
        } else {
            $this->assertSame('Đổi tên từ Excel', $model->name);
        }
    }

    public function test_product_import_matches_names_with_case_and_whitespace_differences_without_losing_accents(): void
    {
        $product = $this->record('products');
        $category = Category::factory()->create(['name' => 'Áo Nữ', 'slug' => 'ao-nu-co-dau']);
        Category::factory()->create(['name' => 'Ao Nu', 'slug' => 'ao-nu-khong-dau']);
        $brand = Brand::factory()->create(['name' => 'Thời Trang Việt']);
        $admin = User::factory()->admin()->create();
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'products'))->streamedContent());
        $book->getActiveSheet()->setCellValue('D2', "  áo \t NỮ  ")->setCellValue('E2', ' THỜI   trang  việt ');

        $response = $this->actingAs($admin)->post(route('admin.excel.import', 'products'), ['file' => $this->upload($book)]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id, 'brand_id' => $brand->id]);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('categories', 3);
        $this->assertDatabaseCount('brands', 2);
    }

    public static function invalidProductRelationNames(): array
    {
        return [
            'missing category' => ['D', null, 'danh mục'],
            'missing brand' => ['E', null, 'thương hiệu'],
            'unknown category' => ['D', 'Danh mục chưa có', 'danh mục'],
            'unknown brand' => ['E', 'Thương hiệu chưa có', 'thương hiệu'],
            'category ID instead of name' => ['D', 'existing-id', 'danh mục'],
            'brand ID instead of name' => ['E', 'existing-id', 'thương hiệu'],
            'category accents are significant' => ['D', 'Ao Nu', 'danh mục'],
        ];
    }

    #[DataProvider('invalidProductRelationNames')]
    public function test_invalid_product_relation_names_report_the_row_and_roll_back_earlier_updates(string $column, ?string $value, string $label): void
    {
        $product = $this->record('products');
        $product->category->update(['name' => 'Áo Nữ']);
        $product->brand->update(['name' => 'Thời Trang Việt']);
        $admin = User::factory()->admin()->create();
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'products'))->streamedContent());
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('C2', 'Không được lưu thay đổi này');
        $sheet->fromArray($sheet->toArray(null, false, false)[1], null, 'A3', true);
        $sheet->setCellValue('A3', null)->setCellValue('B3', 'new-product')->setCellValue('C3', 'Sản phẩm chưa hợp lệ');
        if ($value === 'existing-id') {
            $value = (string) ($column === 'D' ? $product->category_id : $product->brand_id);
        }
        $sheet->setCellValue($column.'3', $value);

        $response = $this->actingAs($admin)->post(route('admin.excel.import', 'products'), ['file' => $this->upload($book)]);

        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString('dòng 3', session('errors')->first('file'));
        $this->assertStringContainsString($label, mb_strtolower(session('errors')->first('file')));
        $this->assertSame($product->name, $product->fresh()->name);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('brands', 1);
        $this->assertSame(7, $product->variants()->first()->stock_quantity);
    }

    public function test_product_import_rejects_ambiguous_category_names_instead_of_selecting_one(): void
    {
        $product = $this->record('products');
        $product->category->update(['name' => 'Áo Nữ']);
        Category::factory()->create(['name' => 'ÁO  NỮ', 'slug' => 'ao-nu-khac']);
        $admin = User::factory()->admin()->create();
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'products'))->streamedContent());
        $book->getActiveSheet()->setCellValue('C2', 'Không được cập nhật');

        $response = $this->actingAs($admin)->post(route('admin.excel.import', 'products'), ['file' => $this->upload($book)]);

        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString('dòng 2', session('errors')->first('file'));
        $this->assertStringContainsString('danh mục', mb_strtolower(session('errors')->first('file')));
        $this->assertSame($product->name, $product->fresh()->name);
        $this->assertSame($product->category_id, $product->fresh()->category_id);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_product_import_can_match_names_that_consist_only_of_digits(): void
    {
        $product = $this->record('products');
        $category = Category::factory()->create(['name' => '12345']);
        $brand = Brand::factory()->create(['name' => '67890']);
        $admin = User::factory()->admin()->create();
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'products'))->streamedContent());
        $book->getActiveSheet()->setCellValue('D2', 12345)->setCellValue('E2', 67890);

        $response = $this->actingAs($admin)->post(route('admin.excel.import', 'products'), ['file' => $this->upload($book)]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id, 'brand_id' => $brand->id]);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_guest_and_customers_cannot_access_excel_operations(): void
    {
        $this->get(route('admin.excel.export', 'brands'))->assertRedirect(route('login'));
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('admin.excel.export', 'brands'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.excel.template', 'suppliers'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.excel.import', 'goods-receipts'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->unverified()->create())->get(route('admin.excel.export', 'brands'))->assertRedirect(route('verification.notice'));
    }

    public function test_invalid_later_row_rolls_back_the_whole_import_and_reports_excel_row(): void
    {
        $book = $this->brandBook();
        $book->getActiveSheet()->fromArray([null, null, null, null, 1], null, 'A3');

        $response = $this->actingAs(User::factory()->admin()->create())->post(route('admin.excel.import', 'brands'), ['file' => $this->upload($book)]);

        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString('dòng 3', session('errors')->first('file'));
        $this->assertDatabaseCount('brands', 0);
    }

    public function test_wrong_headers_empty_files_and_formulas_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $book = $this->brandBook();
        $book->getActiveSheet()->setCellValue('B1', 'Sai tên cột');
        $this->actingAs($admin)->post(route('admin.excel.import', 'brands'), ['file' => $this->upload($book)])->assertSessionHasErrors('file');
        $book = $this->brandBook();
        $book->getActiveSheet()->setCellValue('B2', '=1+1');
        $this->actingAs($admin)->post(route('admin.excel.import', 'brands'), ['file' => $this->upload($book)])->assertSessionHasErrors('file');
        $book = $this->brandBook();
        $book->getActiveSheet()->removeRow(2);
        $this->actingAs($admin)->post(route('admin.excel.import', 'brands'), ['file' => $this->upload($book)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('brands', 0);
    }

    public function test_missing_unknown_and_duplicate_ids_do_not_overwrite_records(): void
    {
        $brand = Brand::factory()->create(['name' => 'Giữ nguyên']);
        $admin = User::factory()->admin()->create();
        $book = $this->brandBook();
        $book->getActiveSheet()->setCellValue('A2', 99999);
        $this->actingAs($admin)->post(route('admin.excel.import', 'brands'), ['file' => $this->upload($book)])->assertSessionHasErrors('file');
        $book = $this->brandBook();
        $book->getActiveSheet()->setCellValue('A2', $brand->id);
        $book->getActiveSheet()->fromArray([$brand->id, 'Lặp lại', null, 'VN', 1], null, 'A3');
        $this->actingAs($admin)->post(route('admin.excel.import', 'brands'), ['file' => $this->upload($book)])->assertSessionHasErrors('file');
        $this->assertSame('Giữ nguyên', $brand->fresh()->name);
        $this->assertDatabaseCount('brands', 1);
    }

    public function test_confirmed_receipt_and_order_coupons_cannot_be_modified_by_excel(): void
    {
        $receipt = GoodsReceipt::factory()->confirmed()->create();
        $admin = User::factory()->admin()->create();
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'goods-receipts'))->streamedContent());
        $this->actingAs($admin)->post(route('admin.excel.import', 'goods-receipts'), ['file' => $this->upload($book)])->assertSessionHasErrors('file');
        $this->assertSame('confirmed', $receipt->fresh()->status);
        $discount = Discount::factory()->coupon()->create();
        $variant = ProductVariant::factory()->create();
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.template', 'discounts'))->streamedContent());
        $book->getActiveSheet()->fromArray([$discount->id, $variant->sku, 'percent', 10, null, '2026-01-01', '2026-12-31', 1], null, 'A2');
        $this->actingAs($admin)->post(route('admin.excel.import', 'discounts'), ['file' => $this->upload($book)])->assertSessionHasErrors('file');
        $this->assertSame('order', $discount->fresh()->scope);
    }

    public function test_nested_rows_must_reference_a_parent_in_the_workbook(): void
    {
        $this->record('products');
        $admin = User::factory()->admin()->create();
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'products'))->streamedContent());
        $book->getSheetByName('Biến thể')->setCellValue('A2', 'missing-parent');

        $this->actingAs($admin)->post(route('admin.excel.import', 'products'), ['file' => $this->upload($book)])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 1);
    }

    public function test_file_size_type_and_row_limits_are_enforced(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.excel.import', 'brands'), ['file' => UploadedFile::fake()->create('large.xlsx', 5121, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])->assertSessionHasErrors('file');
        $this->actingAs($admin)->post(route('admin.excel.import', 'brands'), ['file' => UploadedFile::fake()->createWithContent('fake.xlsx', 'not an excel file')])->assertSessionHasErrors('file');
        $book = $this->brandBook();
        $book->getActiveSheet()->setCellValue('B'.(AdminExcel::MAX_ROWS + 2), 'Too many');
        $this->actingAs($admin)->post(route('admin.excel.import', 'brands'), ['file' => $this->upload($book)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('brands', 0);
    }

    public function test_product_import_preserves_variants_absent_from_the_file(): void
    {
        $product = $this->record('products');
        $variant = $product->variants()->first();
        $admin = User::factory()->admin()->create();
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'products'))->streamedContent());
        $book->getSheetByName('Biến thể')->removeRow(2);
        $book->getActiveSheet()->setCellValue('C2', 'Chỉ cập nhật tên');
        $book->getActiveSheet()->setCellValue('I2', null);

        $this->actingAs($admin)->post(route('admin.excel.import', 'products'), ['file' => $this->upload($book)])->assertSessionHasNoErrors();

        $this->assertSame('Chỉ cập nhật tên', $product->fresh()->name);
        $this->assertSame(1, $product->variants()->count());
        $this->assertNull($variant->fresh()->deleted_at);
        $this->assertSame(7, $variant->fresh()->stock_quantity);
    }

    public function test_duplicate_variant_size_color_and_deleted_sku_return_row_errors(): void
    {
        $product = $this->record('products');
        $variant = $product->variants()->first();
        $admin = User::factory()->admin()->create();
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'products'))->streamedContent());
        $book->getSheetByName('Biến thể')->fromArray([$product->id, null, $variant->size, $variant->color, 'NEW-SKU', 10000, 5, 1], null, 'A3', true);
        $this->actingAs($admin)->post(route('admin.excel.import', 'products'), ['file' => $this->upload($book)])->assertSessionHasErrors('file');
        $this->assertStringContainsString('dòng 2', session('errors')->first('file'));
        $this->assertDatabaseCount('product_variants', 1);

        $deleted = ProductVariant::factory()->create(['sku' => 'DELETED-SKU']);
        $deleted->delete();
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'products'))->streamedContent());
        $book->getSheetByName('Biến thể')->fromArray([$product->id, null, 'Unique', 'Unique', 'DELETED-SKU', 10000, 5, 1], null, 'A3', true);
        $this->actingAs($admin)->post(route('admin.excel.import', 'products'), ['file' => $this->upload($book)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('product_variants', 2);
        $this->assertSame(7, $variant->fresh()->stock_quantity);
    }

    public function test_variant_ids_cannot_be_moved_between_products(): void
    {
        $product = $this->record('products');
        $other = ProductVariant::factory()->create();
        $admin = User::factory()->admin()->create();
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'products'))->streamedContent());
        $book->getSheetByName('Biến thể')->setCellValue('B2', $other->id);

        $this->actingAs($admin)->post(route('admin.excel.import', 'products'), ['file' => $this->upload($book)])->assertSessionHasErrors('file');

        $this->assertSame(1, $product->variants()->count());
        $this->assertNotSame($product->id, $other->fresh()->product_id);
    }

    public function test_discount_import_reuses_custom_validation_and_accepts_excel_dates(): void
    {
        $discount = $this->record('discounts');
        $admin = User::factory()->admin()->create();
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'discounts'))->streamedContent());
        $book->getActiveSheet()->setCellValue('C2', 'percent')->setCellValue('D2', 101);
        $this->actingAs($admin)->post(route('admin.excel.import', 'discounts'), ['file' => $this->upload($book)])->assertSessionHasErrors('file');
        $this->assertStringContainsString('100', session('errors')->first('file'));

        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'discounts'))->streamedContent());
        $book->getActiveSheet()->setCellValue('F2', Date::PHPToExcel(new \DateTimeImmutable('2026-01-01')));
        $book->getActiveSheet()->setCellValue('G2', Date::PHPToExcel(new \DateTimeImmutable('2026-12-31')));
        $book->getActiveSheet()->getStyle('F2:G2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $this->actingAs($admin)->post(route('admin.excel.import', 'discounts'), ['file' => $this->upload($book)])->assertSessionHasNoErrors();
        $this->assertSame('2026-01-01', $discount->fresh()->starts_at->format('Y-m-d'));
    }

    public function test_exports_user_text_as_literal_strings_and_prices_as_numbers(): void
    {
        Brand::factory()->create(['description' => '=HYPERLINK("https://example.com")']);
        $admin = User::factory()->admin()->create();
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'brands'))->streamedContent());
        $this->assertSame(DataType::TYPE_STRING, $book->getActiveSheet()->getCell('C2')->getDataType());
        $book->disconnectWorksheets();
        $this->record('products');
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'products'))->streamedContent());
        $this->assertSame(DataType::TYPE_NUMERIC, $book->getActiveSheet()->getCell('G2')->getDataType());
        $book->disconnectWorksheets();
    }

    public function test_receipt_and_discount_exports_keep_historical_skus_of_deleted_variants(): void
    {
        $receipt = $this->receipt();
        $variant = $receipt->items()->first()->productVariant;
        Discount::factory()->create(['product_variant_id' => $variant->id]);
        $variant->delete();
        $admin = User::factory()->admin()->create();

        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'goods-receipts'))->streamedContent());
        $this->assertSame('TEST-SKU', $book->getSheetByName('Dòng hàng')->getCell('B2')->getValue());
        $book->disconnectWorksheets();
        $book = $this->readBytes($this->actingAs($admin)->get(route('admin.excel.export', 'discounts'))->streamedContent());
        $this->assertSame('TEST-SKU', $book->getActiveSheet()->getCell('B2')->getValue());
        $book->disconnectWorksheets();
    }

    private function record(string $resource): Model
    {
        return match ($resource) {
            'brands' => Brand::factory()->create(),
            'categories' => Category::factory()->create(),
            'suppliers' => Supplier::factory()->create(['phone' => '0901234567']),
            'products' => Product::factory()->has(ProductVariant::factory()->state(['sku' => 'TEST-SKU', 'stock_quantity' => 7]), 'variants')->create(),
            'discounts' => Discount::factory()->create(),
            'goods-receipts' => $this->receipt(),
        };
    }

    private function receipt(): GoodsReceipt
    {
        $variant = ProductVariant::factory()->create(['sku' => 'TEST-SKU', 'stock_quantity' => 7]);
        $receipt = GoodsReceipt::factory()->create(['total_cost' => 75000]);
        $receipt->items()->create(['product_variant_id' => $variant->id, 'quantity' => 3, 'cost_price' => 25000, 'subtotal' => 75000]);

        return $receipt;
    }

    private function brandBook(): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('Dữ liệu')->fromArray([
            ['ID', 'Tên thương hiệu', 'Mô tả', 'Quốc gia', 'Hoạt động (1/0)'],
            [null, 'Thương hiệu tiếng Việt', null, 'VN', 1],
        ]);

        return $book;
    }

    private function upload(Spreadsheet $book): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'excel-test-');
        try {
            (new Xlsx($book))->save($path);

            return UploadedFile::fake()->createWithContent('import.xlsx', file_get_contents($path));
        } finally {
            unlink($path);
            $book->disconnectWorksheets();
        }
    }

    private function readBytes(string $bytes): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'excel-test-');
        try {
            file_put_contents($path, $bytes);

            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }
}
