<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductAiExcelRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Ai\AiProviderContract;
use App\Services\Ai\ExcelImport\MappingParser;
use App\Services\Ai\ExcelImport\MappingPromptBuilder;
use App\Services\Ai\ExcelImport\ProductMapper;
use App\Services\Ai\ExcelImport\SpreadsheetGrid;
use App\Services\Ai\InvalidAiResponseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lets the AI read a spreadsheet in any layout and hands the products it
 * found back to the chat widget. This endpoint saves NOTHING: the widget
 * lists the products and the staff member picks one to fill into the normal
 * product form, then reviews it and presses "Lưu" themselves — the AI never
 * submits anything (human in the loop).
 */
class ProductAiExcelController extends Controller
{
    private const UNAVAILABLE = 'Chưa thể đọc file Excel lúc này. Vui lòng thử lại sau hoặc dùng file mẫu trong mục Nhập từ Excel.';

    /** More than this would make the response (and the chat card) unwieldy; the rest are only counted. */
    private const MAX_PRODUCTS = 100;

    private const LISTED_ISSUES = 12;

    public function __invoke(
        ProductAiExcelRequest $request,
        AiProviderContract $provider,
        MappingPromptBuilder $promptBuilder,
        MappingParser $parser,
    ): JsonResponse {
        $grid = SpreadsheetGrid::fromPath($request->file('file')->getRealPath());

        try {
            $reply = $provider->complete(
                [['role' => 'user', 'content' => [['type' => 'text', 'text' => "Nội dung file Excel:\n\n".$grid->snapshot()]]]],
                $promptBuilder->build(),
            );
        } catch (ConnectionException|RuntimeException) {
            abort(503, self::UNAVAILABLE);
        }

        try {
            $mapping = $parser->parse($reply, $grid);
        } catch (InvalidAiResponseException $exception) {
            Log::warning('Product AI Excel: mapping reply failed to parse.', ['reason' => $exception->getMessage(), 'raw' => Str::limit($reply, 2000)]);
            abort(502, 'AI chưa xác định được cột tên sản phẩm trong file. Hãy kiểm tra file có dòng tiêu đề rõ ràng hoặc dùng file mẫu.');
        }

        $result = (new ProductMapper(
            Category::query()->pluck('name')->all(),
            Brand::query()->pluck('name')->all(),
            Product::query()->pluck('name')->all(),
            ProductVariant::withTrashed()->pluck('sku')->all(),
        ))->map($grid, $mapping);

        $issues = [];
        foreach ($result['products'] as $product) {
            foreach ($product['warnings'] as $warning) {
                $issues[] = $product['name'].': '.$warning;
            }
        }

        return response()->json(['data' => [
            'sheet' => $mapping['sheet'],
            'note' => $mapping['note'],
            'columns' => $this->describeColumns($grid, $mapping),
            'product_count' => count($result['products']),
            'variant_count' => array_sum(array_map(fn (array $product): int => count($product['variants']), $result['products'])),
            'products' => array_map(fn (array $product): array => [
                'name' => $product['name'],
                'category' => $product['category'],
                'brand' => $product['brand'],
                'price' => $product['price'],
                'description' => $product['description'] === null ? null : Str::limit($product['description'], 4000, ''),
                'status' => $product['status'],
                'variants' => array_map(fn (array $variant): array => [
                    'size' => $variant['size'], 'color' => $variant['color'], 'sku' => $variant['sku'], 'price' => $variant['price'],
                ], $product['variants']),
                'warnings' => $product['warnings'],
            ], array_slice($result['products'], 0, self::MAX_PRODUCTS)),
            'skipped_count' => count($result['skipped']),
            'skipped' => array_slice($result['skipped'], 0, self::LISTED_ISSUES),
            'warning_count' => count($issues),
            'warnings' => array_slice($issues, 0, self::LISTED_ISSUES),
        ]]);
    }

    /**
     * The header text found above each mapped column, so the card can say
     * "Tên ← cột B (Tên hàng)" and a wrong guess is easy to spot.
     *
     * @param  array{sheet: string, header_row: int, columns: array<string, string|null>}  $mapping
     * @return array<string, array{column: string, header: string}>
     */
    private function describeColumns(SpreadsheetGrid $grid, array $mapping): array
    {
        $header = $grid->rows($mapping['sheet'])[$mapping['header_row'] - 1] ?? [];
        $described = [];
        foreach ($mapping['columns'] as $field => $letter) {
            if ($letter !== null) {
                $described[$field] = ['column' => $letter, 'header' => trim((string) ($header[MappingParser::columnIndex($letter)] ?? ''))];
            }
        }

        return $described;
    }
}
