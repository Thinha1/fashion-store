<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'products.manage';
    }

    /**
     * Slug is always server-generated from the name, never taken from the
     * client — on create it's derived (with a numeric suffix on collision);
     * on update it stays whatever the brand already has (immutable after
     * creation, per the form's own "không đổi sau khi lưu" hint).
     */
    protected function prepareForValidation(): void
    {
        $brand = $this->route('brand');

        $this->merge([
            'slug' => $brand ? $brand->slug : $this->generateUniqueSlug((string) $this->input('name')),
        ]);
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
            'logo' => ['nullable', 'image', 'max:2048'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'thuong-hieu';
        $slug = $base;

        for ($suffix = 2; Brand::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
