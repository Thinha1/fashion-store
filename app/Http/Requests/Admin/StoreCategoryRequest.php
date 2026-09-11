<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'products.manage';
    }

    protected function prepareForValidation(): void
    {
        $category = $this->route('category');

        if ($category && $this->input('slug') === null && $this->input('name') !== null) {
            $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category?->id;

        return [
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('id', '!=', $categoryId)),
                'not_in:'.($categoryId ?? 0),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($categoryId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_path' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.not_in' => 'Danh mục con không thể chọn chính nó làm cha mẹ.',
        ];
    }
}
