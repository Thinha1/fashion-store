<?php

namespace App\Services\Ai\ExcelImport;

use App\Models\ProductVariant;
use Illuminate\Support\Str;

/**
 * Turns a spreadsheet plus the model's column mapping into importable
 * product drafts, deterministically. Rows that share a product name become
 * the variants of one product (blank names inherit the row above, which is
 * what merged cells look like once read), size/color cells holding lists
 * ("S, M, L") expand into every combination, and prices like "350.000đ" or
 * "350k" are normalized. Anything that cannot be imported safely is reported
 * with its row number instead of being guessed.
 */
class ProductMapper
{
    private const SIZE_ALIASES = ['XXL' => '2XL', 'XXXL' => '3XL', 'XXXXL' => '4XL', '2X' => '2XL', '3X' => '3XL', '4X' => '4XL'];

    /** @var array<string, string> folded name => canonical name */
    private array $categories = [];

    /** @var array<string, string> */
    private array $brands = [];

    /**
     * Names shared by several records. The standard Excel import refuses
     * these outright (it cannot know which record is meant), so they are
     * reported per product here instead of failing the whole file.
     *
     * @var array<string, true>
     */
    private array $ambiguousCategories = [];

    /** @var array<string, true> */
    private array $ambiguousBrands = [];

    /** @var array<string, true> */
    private array $existingProducts = [];

    /** @var array<string, true> upper-cased SKUs already taken (database and this file) */
    private array $takenSkus = [];

    /**
     * @param  list<string>  $categoryNames
     * @param  list<string>  $brandNames
     * @param  list<string>  $existingProductNames
     * @param  list<string>  $existingSkus
     */
    public function __construct(array $categoryNames, array $brandNames, array $existingProductNames, array $existingSkus)
    {
        foreach ($categoryNames as $name) {
            $this->categories[self::fold($name)] ??= $name;
        }
        foreach ($brandNames as $name) {
            $this->brands[self::fold($name)] ??= $name;
        }
        $this->ambiguousCategories = self::duplicates($categoryNames);
        $this->ambiguousBrands = self::duplicates($brandNames);
        foreach ($existingProductNames as $name) {
            $this->existingProducts[self::fold($name)] = true;
        }
        foreach ($existingSkus as $sku) {
            $this->takenSkus[strtoupper($sku)] = true;
        }
    }

    /**
     * @param  array{sheet: string, header_row: int, first_data_row: int, columns: array<string, string|null>}  $mapping
     * @return array{products: list<array<string, mixed>>, skipped: list<array{name: string, row: int, reason: string}>}
     */
    public function map(SpreadsheetGrid $grid, array $mapping): array
    {
        $rows = $grid->rows($mapping['sheet']);
        $columns = array_map(fn (?string $letter): ?int => $letter === null ? null : MappingParser::columnIndex($letter), $mapping['columns']);

        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        $currentKey = null;

        for ($index = $mapping['first_data_row'] - 1; $index < count($rows); $index++) {
            $row = $rows[$index];
            $rowNumber = $index + 1;
            $cell = fn (string $field): mixed => $columns[$field] === null ? null : ($row[$columns[$field]] ?? null);

            $name = self::text($cell('name'));
            $sizeText = self::text($cell('size'));
            $colorText = self::text($cell('color'));
            $skuText = self::text($cell('sku'));
            $variantPrice = self::parsePrice($cell('variant_price'));
            $price = self::parsePrice($cell('price'));
            $hasVariantData = $sizeText !== null || $colorText !== null || $skuText !== null || $variantPrice !== null;

            if ($name === null) {
                if ($currentKey === null || ! $hasVariantData) {
                    continue; // blank or decorative row
                }
                $key = $currentKey;
            } else {
                if (! $hasVariantData && $price === null && self::looksLikeTotalRow($name)) {
                    continue;
                }
                $key = self::fold($name);
                $groups[$key] ??= [
                    'name' => $name, 'row' => $rowNumber, 'category' => null, 'brand' => null, 'description' => null,
                    'price' => null, 'status' => null, 'variants' => [], 'variant_keys' => [], 'warnings' => [],
                ];
                $currentKey = $key;
            }

            $group = &$groups[$key];
            $group['category'] ??= self::text($cell('category'));
            $group['brand'] ??= self::text($cell('brand'));
            $group['description'] ??= self::text($cell('description'));
            $group['status'] ??= self::text($cell('status'));
            $group['price'] ??= $price;

            // A single "price" column that differs between a product's rows is really a
            // per-variant price (e.g. a bigger size costs more): keep it on that variant.
            $rowVariantPrice = $variantPrice ?? ($price !== null && $price !== $group['price'] ? $price : null);
            $this->addVariants($group, $rowNumber, self::splitList($sizeText), self::splitList($colorText), $skuText, $rowVariantPrice);
            unset($group);
        }

        return $this->finalize($groups);
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  list<string>  $sizes
     * @param  list<string>  $colors
     */
    private function addVariants(array &$group, int $row, array $sizes, array $colors, ?string $sku, ?int $price): void
    {
        if ($sizes === [] && $colors === [] && $sku === null && $price === null) {
            return; // product-level row, no variant information
        }
        if ($sizes === []) {
            $group['warnings'][] = 'Dòng '.$row.': thiếu size, bỏ qua biến thể này (size bắt buộc: '.implode(', ', ProductVariant::SIZES).').';

            return;
        }
        $colors = $colors === [] ? [null] : $colors;
        $combinations = count($sizes) * count($colors);

        foreach ($sizes as $rawSize) {
            $size = self::normalizeSize($rawSize);
            if ($size === null) {
                $group['warnings'][] = 'Dòng '.$row.': size "'.$rawSize.'" không hợp lệ (chỉ nhận '.implode(', ', ProductVariant::SIZES).'), bỏ qua.';

                continue;
            }
            foreach ($colors as $rawColor) {
                $color = $rawColor ?? 'Mặc định';
                if ($rawColor === null) {
                    $group['warnings'][] = 'Dòng '.$row.': thiếu màu, dùng "Mặc định".';
                }
                $variantKey = $size.'|'.self::fold($color);
                if (isset($group['variant_keys'][$variantKey])) {
                    $group['warnings'][] = 'Dòng '.$row.': biến thể '.$size.' / '.$color.' bị lặp, giữ dòng đầu.';

                    continue;
                }
                $group['variant_keys'][$variantKey] = true;
                $group['variants'][] = [
                    'size' => $size,
                    'color' => $color,
                    // One SKU cell can only name one variant; for a list it becomes a prefix.
                    'sku' => $sku === null ? null : ($combinations === 1 ? $sku : $sku.'-'.$size.'-'.strtoupper(Str::slug($color))),
                    'price' => $price,
                ];
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @return array{products: list<array<string, mixed>>, skipped: list<array{name: string, row: int, reason: string}>}
     */
    private function finalize(array $groups): array
    {
        $products = [];
        $skipped = [];

        foreach ($groups as $key => $group) {
            $reason = $this->skipReason($key, $group);
            if ($reason !== null) {
                $skipped[] = ['name' => $group['name'], 'row' => $group['row'], 'reason' => $reason];

                continue;
            }

            $variants = [];
            foreach ($group['variants'] as $variant) {
                $generated = $variant['sku'] === null;
                $sku = $this->uniqueSku($variant['sku'] ?? $this->generateSku($group['name'], $variant['size'], $variant['color']), $generated, $group);
                $variants[] = [...$variant, 'sku' => $sku, 'sku_generated' => $generated];
            }

            $price = $group['price'] ?? min(array_filter(array_column($variants, 'price'), fn ($p) => $p !== null));
            $products[] = [
                'name' => $group['name'],
                'row' => $group['row'],
                'category' => $this->categories[self::fold($group['category'])],
                'brand' => $this->brands[self::fold($group['brand'])],
                'description' => $group['description'],
                'price' => $price,
                // Null when the file has no status text: filling the form then leaves its own default alone.
                'status' => $group['status'] === null ? null : self::normalizeStatus($group['status']),
                'variants' => $variants,
                'warnings' => $group['warnings'],
            ];
        }

        return ['products' => $products, 'skipped' => $skipped];
    }

    /** @param array<string, mixed> $group */
    private function skipReason(string $key, array $group): ?string
    {
        if (isset($this->existingProducts[$key])) {
            return 'Đã có sản phẩm trùng tên trong cửa hàng.';
        }
        if ($group['category'] === null) {
            return 'Thiếu danh mục.';
        }
        if (! isset($this->categories[self::fold($group['category'])])) {
            return 'Danh mục "'.$group['category'].'" chưa có trong hệ thống. Thêm danh mục rồi tải lại file.';
        }
        if (isset($this->ambiguousCategories[self::exact($this->categories[self::fold($group['category'])])])) {
            return 'Có nhiều danh mục cùng tên "'.$this->categories[self::fold($group['category'])].'". Đổi tên để phân biệt rồi tải lại file.';
        }
        if ($group['brand'] === null) {
            return 'Thiếu thương hiệu.';
        }
        if (! isset($this->brands[self::fold($group['brand'])])) {
            return 'Thương hiệu "'.$group['brand'].'" chưa có trong hệ thống. Thêm thương hiệu rồi tải lại file.';
        }
        if (isset($this->ambiguousBrands[self::exact($this->brands[self::fold($group['brand'])])])) {
            return 'Có nhiều thương hiệu cùng tên "'.$this->brands[self::fold($group['brand'])].'". Đổi tên để phân biệt rồi tải lại file.';
        }
        $variantPrices = array_filter(array_column($group['variants'], 'price'), fn ($p) => $p !== null);
        if ($group['price'] === null && $variantPrices === []) {
            return 'Thiếu giá bán.';
        }

        return null;
    }

    private function generateSku(string $name, string $size, string $color): string
    {
        $base = strtoupper(Str::slug(Str::limit($name, 30, ''))) ?: 'SP';

        return $base.'-'.$size.'-'.(strtoupper(Str::slug($color)) ?: 'MD');
    }

    /** @param array<string, mixed> $group */
    private function uniqueSku(string $sku, bool $generated, array &$group): string
    {
        $candidate = $sku = mb_substr(strtoupper(trim($sku)), 0, 100);
        $suffix = 1;
        while (isset($this->takenSkus[$candidate])) {
            $candidate = mb_substr($sku, 0, 94).'-'.(++$suffix);
        }
        if ($candidate !== $sku && ! $generated) {
            $group['warnings'][] = 'SKU "'.$sku.'" đã tồn tại, đổi thành "'.$candidate.'".';
        }
        $this->takenSkus[$candidate] = true;

        return $candidate;
    }

    /** Same comparison the standard Excel import uses: case and spacing only, accents matter. */
    private static function exact(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)), 'UTF-8');
    }

    /**
     * @param  list<string>  $names
     * @return array<string, true>
     */
    private static function duplicates(array $names): array
    {
        $counts = array_count_values(array_map(self::exact(...), $names));

        return array_map(fn () => true, array_filter($counts, fn (int $count): bool => $count > 1));
    }

    public static function fold(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', Str::ascii($value))), 'UTF-8');
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /** @return list<string> */
    private static function splitList(?string $value): array
    {
        if ($value === null) {
            return [];
        }
        $parts = preg_split('/\s*(?:[,;\/|&\n]|\svà\s)\s*/u', $value) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $parts), fn (string $part): bool => $part !== '')));
    }

    private static function normalizeSize(string $size): ?string
    {
        $size = strtoupper(str_replace(' ', '', $size));
        $size = self::SIZE_ALIASES[$size] ?? $size;

        return in_array($size, ProductVariant::SIZES, true) ? $size : null;
    }

    private static function normalizeStatus(?string $status): string
    {
        $folded = $status === null ? '' : self::fold($status);
        foreach (['khong', 'ngung', 'archiv', 'het hang', 'inactive', 'false'] as $negative) {
            if (str_contains($folded, $negative)) {
                return 'archived';
            }
        }
        foreach (['dang', 'active', 'con hang', 'true'] as $positive) {
            if (str_contains($folded, $positive)) {
                return 'active';
            }
        }

        // Unknown or missing: imported products stay hidden until someone reviews them.
        return $folded === '1' ? 'active' : 'archived';
    }

    private static function looksLikeTotalRow(string $name): bool
    {
        return (bool) preg_match('/\A(tong|total|cong|subtotal)\b/', self::fold($name));
    }

    public static function parsePrice(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return $value >= 0 ? (int) round($value) : null;
        }
        $text = mb_strtolower(trim((string) $value), 'UTF-8');
        $multiplier = 1;
        if (preg_match('/\d\s*(?:k|nghìn|ngàn)\b/u', $text)) {
            $multiplier = 1000;
        } elseif (preg_match('/\d\s*(?:tr|triệu|m)\b/u', $text)) {
            $multiplier = 1000000;
        }
        $number = (string) preg_replace('/[^\d.,]/u', '', $text);
        if ($number === '') {
            return null;
        }
        $number = preg_match('/\A\d{1,3}(?:[.,]\d{3})+\z/', $number) ? str_replace([',', '.'], '', $number) : str_replace(',', '.', $number);
        if (! is_numeric($number)) {
            return null;
        }
        $amount = (float) $number * $multiplier;

        return $amount >= 0 ? (int) round($amount) : null;
    }
}
