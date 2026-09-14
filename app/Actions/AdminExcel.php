<?php

namespace App\Actions;

use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\GoodsReceiptController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\StoreDiscountRequest;
use App\Http\Requests\Admin\StoreGoodsReceiptRequest;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ZipArchive;

class AdminExcel
{
    public const MAX_ROWS = 5000;

    public function __construct(private Container $container, private Redirector $redirector) {}

    /** @return array<string, array<string, mixed>> */
    public static function resources(): array
    {
        return [
            'brands' => [
                'label' => 'Thương hiệu', 'permission' => 'products.manage',
                'model' => Brand::class, 'request' => StoreBrandRequest::class, 'controller' => BrandController::class, 'parameter' => 'brand',
                'columns' => ['ID' => 'id', 'Tên thương hiệu' => 'name', 'Mô tả' => 'description', 'Quốc gia' => 'country', 'Hoạt động (1/0)' => 'is_active'],
            ],
            'categories' => [
                'label' => 'Danh mục', 'permission' => 'products.manage',
                'model' => Category::class, 'request' => StoreCategoryRequest::class, 'controller' => CategoryController::class, 'parameter' => 'category',
                'columns' => ['ID' => 'id', 'Tên danh mục' => 'name', 'ID danh mục cha' => 'parent_id', 'Mô tả' => 'description', 'Thứ tự' => 'sort_order', 'Hoạt động (1/0)' => 'is_active'],
            ],
            'suppliers' => [
                'label' => 'Nhà cung cấp', 'permission' => 'suppliers.manage',
                'model' => Supplier::class, 'request' => StoreSupplierRequest::class, 'controller' => SupplierController::class, 'parameter' => 'supplier',
                'columns' => ['ID' => 'id', 'Tên nhà cung cấp' => 'name', 'Điện thoại' => 'phone', 'Email' => 'email', 'Địa chỉ' => 'address', 'Mã số thuế' => 'tax_code', 'Hoạt động (1/0)' => 'is_active'],
            ],
            'products' => [
                'label' => 'Sản phẩm', 'permission' => 'products.manage',
                'model' => Product::class, 'request' => StoreProductRequest::class, 'controller' => ProductController::class, 'parameter' => 'product',
                'columns' => ['ID' => 'id', 'Mã liên kết' => '_key', 'Tên sản phẩm' => 'name', 'Tên danh mục' => '_category_name', 'Tên thương hiệu' => '_brand_name', 'Mô tả' => 'description', 'Giá cơ bản' => 'base_price', 'Trạng thái' => 'status', 'Nổi bật (1/0)' => 'is_featured'],
                'children' => ['Biến thể' => ['Mã liên kết' => '_key', 'ID biến thể' => 'id', 'Size' => 'size', 'Màu' => 'color', 'SKU' => 'sku', 'Giá' => 'price', 'Ngưỡng tồn thấp' => 'low_stock_threshold', 'Hoạt động (1/0)' => 'is_active', 'Tồn kho (chỉ xem)' => '_stock']],
            ],
            'discounts' => [
                'label' => 'Giảm giá', 'permission' => 'products.manage',
                'model' => Discount::class, 'request' => StoreDiscountRequest::class, 'controller' => DiscountController::class, 'parameter' => 'discount',
                'columns' => ['ID' => 'id', 'SKU' => '_sku', 'Loại giảm (percent/fixed)' => 'discount_type', 'Giá trị giảm' => 'discount_value', 'Giảm tối đa' => 'max_discount_amount', 'Bắt đầu' => 'starts_at', 'Kết thúc' => 'ends_at', 'Hoạt động (1/0)' => 'is_active'],
            ],
            'goods-receipts' => [
                'label' => 'Phiếu nhập hàng', 'permission' => 'inventory.manage',
                'model' => GoodsReceipt::class, 'request' => StoreGoodsReceiptRequest::class, 'controller' => GoodsReceiptController::class, 'parameter' => 'goodsReceipt',
                'columns' => ['ID' => 'id', 'Mã liên kết' => '_key', 'ID nhà cung cấp' => 'supplier_id', 'Ghi chú' => 'notes', 'Số phiếu (chỉ xem)' => '_number', 'Trạng thái (chỉ xem)' => '_status', 'Tổng chi phí (chỉ xem)' => '_total'],
                'children' => ['Dòng hàng' => ['Mã liên kết' => '_key', 'SKU' => '_sku', 'Số lượng' => 'quantity', 'Giá nhập' => 'cost_price']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function definition(string $resource): array
    {
        return self::resources()[$resource] ?? abort(404);
    }

    public function workbook(string $resource, bool $template = false): Spreadsheet
    {
        $definition = $this->definition($resource);
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('Fashion Store')->setTitle($definition['label']);
        $main = $book->getActiveSheet()->setTitle('Dữ liệu');
        $this->writeHeader($main, array_keys($definition['columns']));
        foreach ($definition['children'] ?? [] as $title => $columns) {
            $sheet = $book->createSheet()->setTitle($title);
            $this->writeHeader($sheet, array_keys($columns));
        }
        if (! $template) {
            $query = $definition['model']::query()->orderBy('id');
            if ($resource === 'discounts') {
                $query->where('scope', 'variant')->with(['productVariant' => fn ($variant) => $variant->withTrashed()]);
            } elseif ($resource === 'products') {
                $query->with(['variants', 'category:id,name', 'brand:id,name']);
            } elseif ($resource === 'goods-receipts') {
                $query->with(['items.productVariant' => fn ($variant) => $variant->withTrashed()]);
            }
            $row = 2;
            $childRow = 2;
            foreach ($query->lazyById(200) as $model) {
                $values = $model->attributesToArray();
                $values['_key'] = (string) $model->id;
                if ($resource === 'products') {
                    $values['_category_name'] = $model->category?->name;
                    $values['_brand_name'] = $model->brand?->name;
                } elseif ($resource === 'discounts') {
                    $values['_sku'] = $model->productVariant?->sku;
                    $values['starts_at'] = $model->starts_at?->format('Y-m-d H:i:s');
                    $values['ends_at'] = $model->ends_at?->format('Y-m-d H:i:s');
                } elseif ($resource === 'goods-receipts') {
                    $values += ['_number' => $model->receipt_number, '_status' => $model->status, '_total' => (float) $model->total_cost];
                }
                $this->writeRow($main, $row++, $definition['columns'], $values);
                if (isset($definition['children'])) {
                    $title = array_key_first($definition['children']);
                    foreach ($resource === 'products' ? $model->variants : $model->items as $child) {
                        $values = $child->attributesToArray() + ['_key' => (string) $model->id];
                        $values['_sku'] = $resource === 'goods-receipts' ? $child->productVariant?->sku : null;
                        $values['_stock'] = $child->stock_quantity;
                        $this->writeRow($book->getSheetByName($title), $childRow++, $definition['children'][$title], $values);
                    }
                }
            }
        }
        $this->addReferenceSheet($book, $resource);
        $guide = $book->createSheet()->setTitle('Hướng dẫn');
        $this->writeHeader($guide, ['Hướng dẫn nhập Excel — '.$definition['label']]);
        $instructions = [
            'Điền sheet Dữ liệu; giữ nguyên tên sheet và tên cột. File mẫu không có dữ liệu giả.',
            'ID trống: thêm mới. ID có sẵn: cập nhật đúng bản ghi đó. Không xóa bản ghi bị thiếu trong file.',
            'Hoạt động / Nổi bật: nhập 1 hoặc 0; bỏ trống để giữ hiện trạng hoặc dùng mặc định khi thêm mới. Điện thoại và SKU nên để kiểu Text để giữ số 0 đầu.',
            $resource === 'products'
                ? 'Nhập Tên danh mục và Tên thương hiệu có sẵn trong sheet Tham chiếu, không nhập ID. Không phân biệt chữ hoa/thường; giữ đúng dấu tiếng Việt. Nếu tên chưa có, thêm ở mục quản trị tương ứng trước khi nhập sản phẩm.'
                : 'ID liên quan và SKU có thể tra trong sheet Tham chiếu. Tiền và số lượng nhập bằng số, không kèm ký hiệu tiền.',
            'Mã liên kết: một mã tự đặt duy nhất cho mỗi dòng trong Dữ liệu; dùng cùng mã tại sheet con.',
            'Sản phẩm: nhập biến thể tại sheet Biến thể; không xóa biến thể bị thiếu. Tồn kho chỉ để xem, biến thể mới có tồn kho 0. Tiền và số lượng nhập bằng số, không kèm ký hiệu tiền.',
            'Phiếu nhập: nhập từng SKU tại sheet Dòng hàng. Phiếu mới luôn là nháp; không cập nhật phiếu đã xác nhận.',
            'Phiếu nháp cập nhật: sheet Dòng hàng là toàn bộ các dòng muốn giữ lại trong phiếu.',
            'Giảm giá: loại percent hoặc fixed; ngày giờ theo YYYY-MM-DD HH:MM:SS. Chỉ giảm giá biến thể được nhập/xuất.',
            'Không nhập công thức Excel. Ảnh và logo tiếp tục được quản lý trên biểu mẫu.',
            'Tối đa 5 MB và '.self::MAX_ROWS.' dòng dữ liệu mỗi file. Có lỗi thì toàn bộ file không được lưu; sửa dòng báo lỗi rồi nhập lại.',
        ];
        foreach ($instructions as $index => $instruction) {
            $guide->setCellValueExplicit('A'.($index + 2), $instruction, DataType::TYPE_STRING);
        }
        $guide->getColumnDimension('A')->setWidth(110);
        $guide->getStyle('A2:A12')->getAlignment()->setWrapText(true);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    public function import(string $resource, UploadedFile $file, Request $source): array
    {
        $definition = $this->definition($resource);
        $book = $this->readFile($file, array_merge(['Dữ liệu'], array_keys($definition['children'] ?? [])));
        try {
            $rows = $this->readRows($book, 'Dữ liệu', $definition['columns']);
            if ($rows === []) {
                throw ValidationException::withMessages(['file' => 'Sheet Dữ liệu chưa có dòng nào để nhập.']);
            }
            $children = [];
            $total = count($rows);
            foreach ($definition['children'] ?? [] as $title => $columns) {
                $children = $this->readRows($book, $title, $columns);
                $total += count($children);
            }
            if ($total > self::MAX_ROWS) {
                throw ValidationException::withMessages(['file' => 'File vượt quá '.self::MAX_ROWS.' dòng dữ liệu.']);
            }
            $this->validateLinks($rows, $children, isset($definition['children']));

            return DB::transaction(function () use ($resource, $definition, $rows, $children, $source): array {
                $result = ['created' => 0, 'updated' => 0];
                $seenIds = [];
                $categoryNames = $resource === 'products' ? $this->indexNames(Category::class) : [];
                $brandNames = $resource === 'products' ? $this->indexNames(Brand::class) : [];
                foreach ($rows as $rowNumber => $data) {
                    try {
                        $model = $this->findModel($definition, $data['id'], $seenIds);
                        if ($resource === 'goods-receipts' && $model && $model->status !== 'draft') {
                            throw ValidationException::withMessages(['id' => 'Phiếu đã xác nhận không thể cập nhật bằng Excel.']);
                        }
                        if ($resource === 'discounts' && $model && $model->scope !== 'variant') {
                            throw ValidationException::withMessages(['id' => 'ID này không phải giảm giá theo biến thể.']);
                        }
                        foreach (['is_active' => 1, 'is_featured' => 0, 'sort_order' => 0, 'status' => 'draft'] as $field => $default) {
                            if (array_key_exists($field, $data) && $data[$field] === null) {
                                $data[$field] = $model?->getAttribute($field) ?? $default;
                            }
                        }
                        if ($resource === 'discounts') {
                            $data['product_variant_id'] = $this->variantId($data['_sku']);
                            $data['scope'] = 'variant';
                        } elseif ($resource === 'products') {
                            $data['category_id'] = $this->resolveName($categoryNames, $data['_category_name'], 'Tên danh mục', 'Danh mục');
                            $data['brand_id'] = $this->resolveName($brandNames, $data['_brand_name'], 'Tên thương hiệu', 'Thương hiệu');
                            $data['variants'] = $this->productVariants($model, $data['_key'], $children);
                        } elseif ($resource === 'goods-receipts') {
                            $data['items'] = $this->receiptItems($data['_key'], $children);
                            if ($data['items'] === []) {
                                throw ValidationException::withMessages(['items' => 'Phiếu nhập phải có ít nhất một dòng hàng.']);
                            }
                        }
                        unset($data['id']);
                        $this->saveUsingForm($definition, $data, $model, $source);
                        $result[$model ? 'updated' : 'created']++;
                    } catch (ValidationException $exception) {
                        $messages = [];
                        foreach ($exception->errors() as $field => $errors) {
                            foreach ($errors as $error) {
                                $messages[] = 'Dữ liệu, dòng '.$rowNumber.' ('.$field.'): '.$error;
                            }
                        }
                        throw ValidationException::withMessages(['file' => $messages]);
                    } catch (UniqueConstraintViolationException $exception) {
                        $field = match ($resource) {
                            'products' => 'SKU hoặc cặp Size/Màu',
                            'suppliers' => 'Mã số thuế',
                            'brands' => 'Tên thương hiệu',
                            default => 'Giá trị duy nhất',
                        };
                        throw ValidationException::withMessages(['file' => 'Dữ liệu, dòng '.$rowNumber.': '.$field.' đã được sử dụng, có thể ở bản ghi đã xóa. Hãy kiểm tra và đổi giá trị.']);
                    }
                }

                return $result;
            });
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function findModel(array $definition, mixed $id, array &$seen): ?Model
    {
        if ($id === null || $id === '') {
            return null;
        }
        if (! ctype_digit((string) $id) || (int) $id < 1) {
            throw ValidationException::withMessages(['id' => 'ID phải là số nguyên dương hoặc để trống.']);
        }
        if (isset($seen[(int) $id])) {
            throw ValidationException::withMessages(['id' => 'ID bị lặp lại trong file.']);
        }
        $seen[(int) $id] = true;
        $model = $definition['model']::query()->lockForUpdate()->find((int) $id);
        if (! $model) {
            throw ValidationException::withMessages(['id' => 'Không tìm thấy bản ghi có ID '.$id.'.']);
        }

        return $model;
    }

    /** Reuse the interactive form's validation and persistence, including custom after-validation rules. */
    private function saveUsingForm(array $definition, array $data, ?Model $model, Request $source): void
    {
        $requestClass = $definition['request'];
        $request = $requestClass::createFrom(Request::create('/', 'POST', $data), new $requestClass);
        $route = clone $source->route();
        $route->setParameter($definition['parameter'], $model);
        $request->setContainer($this->container)->setRedirector($this->redirector);
        $request->setUserResolver($source->getUserResolver());
        $request->setRouteResolver(fn () => $route);
        $request->validateResolved();
        $controller = $this->container->make($definition['controller']);
        if ($model) {
            $controller->update($request, $model);
        } else {
            $controller->store($request);
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, list<int>>
     */
    private function indexNames(string $modelClass): array
    {
        $names = [];
        foreach ($modelClass::query()->select(['id', 'name'])->lazyById(200) as $model) {
            $names[$this->normalizeName($model->name)][] = (int) $model->id;
        }

        return $names;
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)), 'UTF-8');
    }

    /** @param array<string, list<int>> $names */
    private function resolveName(array $names, mixed $value, string $column, string $section): int
    {
        $name = $this->normalizeName((string) $value);
        if ($name === '') {
            throw ValidationException::withMessages([$column => 'Điền '.$column.' có sẵn trong sheet Tham chiếu.']);
        }
        $matches = $names[$name] ?? [];
        if ($matches === []) {
            throw ValidationException::withMessages([$column => 'Không tìm thấy tên "'.$value.'". Kiểm tra sheet Tham chiếu hoặc thêm tên này tại mục '.$section.' trước khi nhập.']);
        }
        if (count($matches) > 1) {
            throw ValidationException::withMessages([$column => 'Tên "'.$value.'" bị trùng ở nhiều bản ghi. Đổi tên để phân biệt tại mục '.$section.', rồi tải lại file mẫu.']);
        }

        return $matches[0];
    }

    private function variantId(mixed $sku): int
    {
        $sku = trim((string) $sku);
        $id = ProductVariant::query()->whereIn('sku', array_unique([$sku, strtoupper($sku)]))->value('id');
        if (! $id) {
            throw ValidationException::withMessages(['sku' => 'Không tìm thấy biến thể có SKU '.(string) $sku.'.']);
        }

        return (int) $id;
    }

    private function productVariants(?Model $product, string $key, array $children): array
    {
        $existing = $product ? $product->variants()->lockForUpdate()->get()->keyBy('id') : collect();
        $variants = $existing->map(fn ($variant) => $variant->only(['size', 'color', 'sku', 'price', 'stock_quantity', 'low_stock_threshold', 'is_active']))->all();
        $seen = [];
        foreach ($children as $number => $row) {
            if ($row['_key'] !== $key) {
                continue;
            }
            $id = $row['id'];
            if ($id !== null && (! ctype_digit((string) $id) || ! $existing->has((int) $id))) {
                throw ValidationException::withMessages(['variants' => 'Biến thể, dòng '.$number.': ID không thuộc sản phẩm này.']);
            }
            if ($id !== null && isset($seen[(int) $id])) {
                throw ValidationException::withMessages(['variants' => 'Biến thể, dòng '.$number.': ID bị lặp lại.']);
            }
            if ($id !== null) {
                $seen[(int) $id] = true;
            }
            $row['is_active'] ??= $id !== null ? (int) $existing[(int) $id]->is_active : 1;
            $row['low_stock_threshold'] ??= $id !== null ? $existing[(int) $id]->low_stock_threshold : 5;
            $row['stock_quantity'] = $id !== null ? $existing[(int) $id]->stock_quantity : 0;
            unset($row['id'], $row['_key'], $row['_stock']);
            $variants[$id !== null ? (int) $id : 'new-'.$number] = $row;
        }

        return $variants;
    }

    private function receiptItems(string $key, array $children): array
    {
        $items = [];
        foreach ($children as $number => $row) {
            if ($row['_key'] === $key) {
                $items[] = ['product_variant_id' => $this->variantId($row['_sku']), 'quantity' => $row['quantity'], 'cost_price' => $row['cost_price']];
            }
        }

        return $items;
    }

    private function validateLinks(array $rows, array $children, bool $required): void
    {
        if (! $required) {
            return;
        }
        $keys = [];
        foreach ($rows as $number => $row) {
            $key = (string) ($row['_key'] ?? '');
            if ($key === '' || isset($keys[$key])) {
                throw ValidationException::withMessages(['file' => 'Dữ liệu, dòng '.$number.': Mã liên kết phải có giá trị và không được lặp lại.']);
            }
            $keys[$key] = true;
        }
        foreach ($children as $number => $row) {
            if (! isset($keys[$row['_key'] ?? ''])) {
                throw ValidationException::withMessages(['file' => 'Sheet con, dòng '.$number.': Mã liên kết không tồn tại trong Dữ liệu.']);
            }
        }
    }

    private function readFile(UploadedFile $file, array $sheets): Spreadsheet
    {
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages(['file' => 'File không phải Excel .xlsx hợp lệ.']);
        }
        try {
            $expandedSize = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $expandedSize += $zip->statIndex($index)['size'];
            }
            if ($expandedSize > 50 * 1024 * 1024 || $zip->numFiles > 1000) {
                throw ValidationException::withMessages(['file' => 'File Excel quá lớn. Hãy chia thành các file nhỏ hơn.']);
            }
        } finally {
            $zip->close();
        }
        try {
            $reader = new Xlsx;
            $info = $reader->listWorksheetInfo($file->getRealPath());
            if (count($info) > 12) {
                throw ValidationException::withMessages(['file' => 'File có quá nhiều sheet. Hãy dùng file mẫu.']);
            }
            foreach ($info as $sheet) {
                if (in_array($sheet['worksheetName'], $sheets, true) && ($sheet['totalRows'] > self::MAX_ROWS + 1 || $sheet['totalColumns'] > 32)) {
                    throw ValidationException::withMessages(['file' => 'Sheet dữ liệu quá lớn. Tối đa '.self::MAX_ROWS.' dòng và 32 cột.']);
                }
            }
            $reader->setReadEmptyCells(false)->setLoadSheetsOnly($sheets);

            return $reader->load($file->getRealPath());
        } catch (Exception $exception) {
            throw ValidationException::withMessages(['file' => 'Không đọc được file Excel. Hãy tải file mẫu và lưu lại dưới định dạng .xlsx.']);
        }
    }

    private function readRows(Spreadsheet $book, string $title, array $columns): array
    {
        $sheet = $book->getSheetByName($title);
        if (! $sheet) {
            throw ValidationException::withMessages(['file' => 'Thiếu sheet '.$title.'. Hãy dùng file mẫu của mục này.']);
        }
        $headers = [];
        for ($column = 1; $column <= count($columns); $column++) {
            $headers[] = trim((string) $sheet->getCell([$column, 1])->getValue());
        }
        if ($headers !== array_keys($columns)) {
            throw ValidationException::withMessages(['file' => 'Tên hoặc thứ tự cột trong sheet '.$title.' không đúng. Hãy dùng file mẫu.']);
        }
        $rows = [];
        for ($number = 2; $number <= $sheet->getHighestDataRow(); $number++) {
            $data = [];
            foreach (array_values($columns) as $index => $field) {
                $cell = $sheet->getCell([$index + 1, $number]);
                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    throw ValidationException::withMessages(['file' => $title.', dòng '.$number.': Không dùng công thức trong dữ liệu nhập.']);
                }
                $value = $cell->getValue();
                if ($value !== null && Date::isDateTime($cell) && is_numeric($value)) {
                    $value = Date::excelToDateTimeObject($value)->format('Y-m-d H:i:s');
                }
                if (is_string($value)) {
                    $value = trim($value);
                }
                if (in_array($field, ['phone', 'sku', '_sku', '_key', '_category_name', '_brand_name', 'tax_code', 'size', 'color'], true) && $value !== null) {
                    $value = (string) $value;
                }
                $data[$field] = $value === '' ? null : $value;
            }
            if (array_filter($data, fn ($value) => $value !== null) !== []) {
                $rows[$number] = $data;
            }
        }

        return $rows;
    }

    private function writeHeader(Worksheet $sheet, array $headers): void
    {
        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit([$index + 1, 1], $header, DataType::TYPE_STRING);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($index === 0 ? 12 : 24);
        }
        $last = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:'.$last.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '175B60']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$last.'1');
    }

    private function writeRow(Worksheet $sheet, int $number, array $columns, array $values): void
    {
        foreach (array_values($columns) as $index => $field) {
            $value = $values[$field] ?? null;
            if ($value !== null) {
                if (in_array($field, ['base_price', 'price', 'cost_price', 'discount_value', 'max_discount_amount', '_total'], true)) {
                    $value = (float) $value;
                    $sheet->getStyle([$index + 1, $number])->getNumberFormat()->setFormatCode('#,##0.00');
                }
                $type = is_bool($value) || is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING;
                $sheet->setCellValueExplicit([$index + 1, $number], is_bool($value) ? (int) $value : $value, $type);
            }
        }
        $last = Coordinate::stringFromColumnIndex(count($columns));
        $sheet->setAutoFilter('A1:'.$last.$number);
    }

    private function addReferenceSheet(Spreadsheet $book, string $resource): void
    {
        $sources = match ($resource) {
            'categories' => [['Danh mục', Category::class]],
            'products' => [['Danh mục', Category::class], ['Thương hiệu', Brand::class]],
            'goods-receipts' => [['Nhà cung cấp', Supplier::class], ['Biến thể', ProductVariant::class]],
            'discounts' => [['Biến thể', ProductVariant::class]],
            default => [],
        };
        if ($sources === []) {
            return;
        }
        $sheet = $book->createSheet()->setTitle('Tham chiếu');
        $columns = $resource === 'products'
            ? ['Loại' => 'type', 'Tên' => 'name']
            : ['Loại' => 'type', 'ID' => 'id', 'Tên' => 'name', 'SKU' => 'sku'];
        $this->writeHeader($sheet, array_keys($columns));
        $number = 2;
        foreach ($sources as [$type, $class]) {
            $query = $class::query()->orderBy('id');
            if ($class === ProductVariant::class) {
                $query->with('product:id,name');
            }
            foreach ($query->lazyById(200) as $model) {
                $this->writeRow($sheet, $number++, $columns, ['type' => $type, 'id' => $model->id, 'name' => $class === ProductVariant::class ? $model->product?->name.' — '.$model->size.' / '.$model->color : $model->name, 'sku' => $model->sku]);
            }
        }
    }
}
