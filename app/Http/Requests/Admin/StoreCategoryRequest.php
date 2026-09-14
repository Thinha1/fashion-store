<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'products.manage';
    }

    /**
     * Slug is always server-generated from the name, never taken from the
     * client — on create it's derived (with a numeric suffix on collision);
     * on update it stays whatever the category already has (immutable
     * after creation).
     */
    protected function prepareForValidation(): void
    {
        $category = $this->route('category');

        $this->merge([
            'slug' => $category ? $category->slug : $this->generateUniqueSlug((string) $this->input('name')),
        ]);
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

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'danh-muc';
        $slug = $base;

        for ($suffix = 2; Category::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
