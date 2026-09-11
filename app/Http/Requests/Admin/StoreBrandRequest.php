<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'products.manage';
    }

    protected function prepareForValidation(): void
    {
        $brand = $this->route('brand');

        if ($brand && $this->input('slug') === null && $this->input('name') !== null) {
            $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        $brand = $this->route('brand');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($brand?->id)],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('brands', 'slug')->ignore($brand?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo_path' => ['nullable', 'string', 'max:2048'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}
