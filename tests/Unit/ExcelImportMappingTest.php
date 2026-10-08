<?php

namespace Tests\Unit;

use App\Services\Ai\ExcelImport\MappingParser;
use App\Services\Ai\ExcelImport\ProductMapper;
use App\Services\Ai\ExcelImport\SpreadsheetGrid;
use App\Services\Ai\InvalidAiResponseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExcelImportMappingTest extends TestCase
{
    /** @return array<string, array{0: mixed, 1: int|null}> */
    public static function prices(): array
    {
        return [
            'plain number' => [350000, 350000],
            'float' => [350000.0, 350000],
            'dotted thousands with currency' => ['350.000đ', 350000],
            'comma thousands' => ['1,250,000', 1250000],
            'k suffix' => ['350k', 350000],
            'decimal k' => ['299.5k', 299500],
            'millions' => ['1,2tr', 1200000],
            'word millions' => ['2 triệu', 2000000],
            'vnd text' => ['350000 VND', 350000],
            'empty' => ['', null],
            'text only' => ['liên hệ', null],
            'negative number' => [-5, null],
        ];
    }

    #[DataProvider('prices')]
    public function test_prices_are_normalized(mixed $input, ?int $expected): void
    {
        $this->assertSame($expected, ProductMapper::parsePrice($input));
    }

    private function mapper(): ProductMapper
    {
        return new ProductMapper(['Áo thun'], ['Mộc Daily'], [], []);
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     * @param  array<string, string|null>  $columns
     * @return array{products: list<array<string, mixed>>, skipped: list<array<string, mixed>>}
     */
    private function map(array $rows, array $columns): array
    {
        $columns += array_fill_keys(['name', 'category', 'brand', 'description', 'price', 'size', 'color', 'sku', 'variant_price', 'status'], null);

        return $this->mapper()->map(SpreadsheetGrid::fromArray(['S' => $rows]), ['sheet' => 'S', 'header_row' => 1, 'first_data_row' => 2, 'columns' => $columns]);
    }

    public function test_blank_names_inherit_the_row_above_like_merged_cells(): void
    {
        $result = $this->map([
            ['Tên', 'Loại', 'Hãng', 'Giá', 'Size', 'Màu'],
            ['Áo A', 'Áo thun', 'Mộc Daily', 100000, 'M', 'Đen'],
            [null, null, null, null, 'L', 'Đen'],
            ['Áo B', 'Áo thun', 'Mộc Daily', 200000, 'S', 'Trắng'],
        ], ['name' => 'A', 'category' => 'B', 'brand' => 'C', 'price' => 'D', 'size' => 'E', 'color' => 'F']);

        $this->assertSame(['Áo A', 'Áo B'], array_column($result['products'], 'name'));
        $this->assertSame(['M', 'L'], array_column($result['products'][0]['variants'], 'size'));
        $this->assertSame(200000, $result['products'][1]['price']);
    }

    public function test_the_same_name_on_distant_rows_still_forms_one_product(): void
    {
        $result = $this->map([
            ['Tên', 'Loại', 'Hãng', 'Giá', 'Size', 'Màu'],
            ['Áo A', 'Áo thun', 'Mộc Daily', 100000, 'M', 'Đen'],
            ['Áo B', 'Áo thun', 'Mộc Daily', 200000, 'S', 'Trắng'],
            ['áo a', 'Áo thun', 'Mộc Daily', 100000, 'L', 'Đen'],
        ], ['name' => 'A', 'category' => 'B', 'brand' => 'C', 'price' => 'D', 'size' => 'E', 'color' => 'F']);

        $this->assertCount(2, $result['products']);
        $this->assertSame(['M', 'L'], array_column($result['products'][0]['variants'], 'size'));
    }

    public function test_total_and_blank_rows_are_ignored(): void
    {
        $result = $this->map([
            ['Tên', 'Loại', 'Hãng', 'Giá'],
            ['Áo A', 'Áo thun', 'Mộc Daily', 100000],
            [],
            ['Tổng cộng', null, null, null],
        ], ['name' => 'A', 'category' => 'B', 'brand' => 'C', 'price' => 'D']);

        $this->assertCount(1, $result['products']);
        $this->assertSame([], $result['skipped']);
    }

    public function test_a_product_without_variant_columns_imports_without_variants(): void
    {
        $result = $this->map([['Tên', 'Loại', 'Hãng', 'Giá'], ['Áo A', 'Áo thun', 'Mộc Daily', '99k']], ['name' => 'A', 'category' => 'B', 'brand' => 'C', 'price' => 'D']);

        $this->assertSame([], $result['products'][0]['variants']);
        $this->assertSame(99000, $result['products'][0]['price']);
        $this->assertNull($result['products'][0]['status']);
    }

    public function test_a_price_column_that_differs_between_rows_becomes_a_per_variant_price(): void
    {
        $result = $this->map([
            ['Tên', 'Loại', 'Hãng', 'Size', 'Giá'],
            ['Áo A', 'Áo thun', 'Mộc Daily', 'M', '199.000đ'],
            [null, null, null, 'L', '199.000đ'],
            [null, null, null, '2XL', '219.000đ'],
        ], ['name' => 'A', 'category' => 'B', 'brand' => 'C', 'size' => 'D', 'price' => 'E']);

        $this->assertSame(199000, $result['products'][0]['price']);
        $this->assertSame([null, null, 219000], array_column($result['products'][0]['variants'], 'price'));
    }

    public function test_base_price_falls_back_to_the_cheapest_variant_price(): void
    {
        $result = $this->map([
            ['Tên', 'Loại', 'Hãng', 'Size', 'Giá size'],
            ['Áo A', 'Áo thun', 'Mộc Daily', 'M', 150000],
            [null, null, null, 'L', 120000],
        ], ['name' => 'A', 'category' => 'B', 'brand' => 'C', 'size' => 'D', 'variant_price' => 'E']);

        $this->assertSame(120000, $result['products'][0]['price']);
        $this->assertSame([150000, 120000], array_column($result['products'][0]['variants'], 'price'));
    }

    /** @return array<string, array{0: string|null, 1: string}> */
    public static function statuses(): array
    {
        return [
            'selling' => ['Đang kinh doanh', 'active'],
            'english' => ['active', 'active'],
            'stopped' => ['Ngừng bán', 'archived'],
            'not selling' => ['Không kinh doanh', 'archived'],
            'inactive is not active' => ['inactive', 'archived'],
            'unknown stays hidden' => ['???', 'archived'],
            'missing stays unset' => [null, null],
        ];
    }

    #[DataProvider('statuses')]
    public function test_status_text_is_understood_and_left_unset_when_absent(?string $text, ?string $expected): void
    {
        $result = $this->map([['Tên', 'Loại', 'Hãng', 'Giá', 'Tình trạng'], ['Áo A', 'Áo thun', 'Mộc Daily', 1000, $text]], ['name' => 'A', 'category' => 'B', 'brand' => 'C', 'price' => 'D', 'status' => 'E']);

        $this->assertSame($expected, $result['products'][0]['status']);
    }

    public function test_parser_accepts_a_fenced_answer_and_drops_columns_it_cannot_trust(): void
    {
        $grid = SpreadsheetGrid::fromArray(['Giá' => [['Tên', 'Giá', 'Màu'], ['A', 1, 'x']]]);
        $raw = "```json\n".json_encode(['sheet' => 'Sai tên sheet', 'header_row' => 99, 'first_data_row' => 0, 'columns' => [
            'name' => 'a', 'price' => 'B', 'color' => 'B', 'size' => 'Z', 'sku' => '1', 'brand' => 'AAA',
        ]])."\n```";

        $mapping = (new MappingParser)->parse($raw, $grid);

        $this->assertSame('Giá', $mapping['sheet'], 'a single-sheet file does not depend on the model repeating the name');
        $this->assertSame(1, $mapping['header_row']);
        $this->assertSame(2, $mapping['first_data_row']);
        $this->assertSame('A', $mapping['columns']['name']);
        $this->assertSame('B', $mapping['columns']['price']);
        $this->assertNull($mapping['columns']['color'], 'a column already claimed by another field is dropped');
        $this->assertNull($mapping['columns']['size'], 'a letter beyond the sheet width is dropped');
        $this->assertNull($mapping['columns']['sku']);
        $this->assertNull($mapping['columns']['brand']);
    }

    public function test_parser_rejects_a_wrong_sheet_when_the_file_has_several(): void
    {
        $grid = SpreadsheetGrid::fromArray(['Một' => [['Tên']], 'Hai' => [['Tên']]]);

        $this->expectException(InvalidAiResponseException::class);

        (new MappingParser)->parse(json_encode(['sheet' => 'Ba', 'columns' => ['name' => 'A']]), $grid);
    }

    public function test_parser_requires_the_name_column(): void
    {
        $this->expectException(InvalidAiResponseException::class);

        (new MappingParser)->parse(json_encode(['sheet' => 'S', 'columns' => ['price' => 'A']]), SpreadsheetGrid::fromArray(['S' => [['x']]]));
    }

    public function test_column_letters_convert_to_indexes(): void
    {
        $this->assertSame(0, MappingParser::columnIndex('A'));
        $this->assertSame(25, MappingParser::columnIndex('Z'));
        $this->assertSame(26, MappingParser::columnIndex('AA'));
    }

    public function test_snapshot_labels_columns_and_truncates_long_cells(): void
    {
        $snapshot = SpreadsheetGrid::fromArray(['Bảng giá' => [['Tên', str_repeat('x', 200)], ['Áo', 5]]])->snapshot();

        $this->assertStringContainsString('Sheet "Bảng giá" — 2 dòng, 2 cột (A–B)', $snapshot);
        $this->assertStringContainsString('Dòng | A | B', $snapshot);
        $this->assertStringContainsString('2 | Áo | 5', $snapshot);
        $this->assertStringNotContainsString(str_repeat('x', 100), $snapshot);
    }
}
