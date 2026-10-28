<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductVariant;
use App\Models\SizeChart;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creating and editing a size chart (/admin/bang-size).
 */
class SaveSizeChartRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'catalog.manage';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'note' => ['nullable', 'string', 'max:1000'],
            'columns' => ['nullable', 'array', 'max:8'],
            'columns.*' => ['nullable', 'string', 'max:40'],
            'rows' => ['required', 'array', 'min:1', 'max:'.count(ProductVariant::SIZES)],
            'rows.*.size' => ['required', 'distinct', Rule::in(ProductVariant::SIZES)],
            'rows.*.height_min' => ['nullable', 'integer', 'between:50,250'],
            'rows.*.height_max' => ['nullable', 'integer', 'between:50,250', 'gte:rows.*.height_min'],
            'rows.*.weight_min' => ['nullable', 'integer', 'between:10,250'],
            'rows.*.weight_max' => ['nullable', 'integer', 'between:10,250', 'gte:rows.*.weight_min'],
            'rows.*.values' => ['nullable', 'array'],
            'rows.*.values.*' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'Bảng size cần ít nhất một dòng.',
            'rows.*.size.distinct' => 'Mỗi size chỉ có một dòng.',
            'rows.*.height_max.gte' => 'Chiều cao tối đa phải lớn hơn hoặc bằng tối thiểu.',
            'rows.*.weight_max.gte' => 'Cân nặng tối đa phải lớn hơn hoặc bằng tối thiểu.',
        ];
    }

    /**
     * Two charts covering the same brand and category would make the
     * automatic choice (SizeChartResolver) arbitrary.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $current = $this->route('sizeChart');
            $duplicate = SizeChart::query()
                ->where('brand_id', $this->input('brand_id') ?: null)
                ->where('category_id', $this->input('category_id') ?: null)
                ->when($current, fn ($query) => $query->whereKeyNot($current->id))
                ->first();

            if ($duplicate !== null) {
                $validator->errors()->add('category_id', "Đã có bảng size \"{$duplicate->name}\" cho đúng thương hiệu và danh mục này.");
            }
        });
    }

    /**
     * The validated chart, with blank measurement columns dropped together
     * with their values, and rows in size order.
     *
     * @return array{name: string, brand_id: ?int, category_id: ?int, note: ?string, columns: list<string>, rows: list<array<string, mixed>>}
     */
    public function chartData(): array
    {
        $data = $this->validated();
        $columns = array_map(fn (?string $label): string => trim((string) $label), array_values($data['columns'] ?? []));
        $kept = array_keys(array_filter($columns, fn (string $label): bool => $label !== ''));
        $order = array_flip(ProductVariant::SIZES);
        $number = fn (mixed $value): ?int => $value === null || $value === '' ? null : (int) $value;

        $rows = array_map(function (array $row) use ($kept, $number): array {
            $values = array_values($row['values'] ?? []);

            return [
                'size' => $row['size'],
                'height_min' => $number($row['height_min'] ?? null),
                'height_max' => $number($row['height_max'] ?? null),
                'weight_min' => $number($row['weight_min'] ?? null),
                'weight_max' => $number($row['weight_max'] ?? null),
                'values' => array_map(fn (int $index): string => trim((string) ($values[$index] ?? '')), $kept),
            ];
        }, array_values($data['rows']));
        usort($rows, fn (array $a, array $b): int => $order[$a['size']] <=> $order[$b['size']]);

        return [
            'name' => trim($data['name']),
            'brand_id' => $number($data['brand_id'] ?? null),
            'category_id' => $number($data['category_id'] ?? null),
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'columns' => array_values(array_map(fn (int $index): string => $columns[$index], $kept)),
            'rows' => $rows,
        ];
    }
}
