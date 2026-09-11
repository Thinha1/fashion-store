<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductVariant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'products.manage';
    }

    protected function prepareForValidation(): void
    {
        $product = $this->route('product');

        // Auto-generate slug from name when not provided on create.
        if (! $product && $this->input('slug') === null && $this->input('name') !== null) {
            $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')->ignore($productId)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')],
            'description' => ['nullable', 'string', 'max:5000'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'is_featured' => ['boolean'],

            'variants' => ['array'],
            'variants.*.size' => ['required', 'string', 'max:50'],
            'variants.*.color' => ['required', 'string', 'max:100'],
            'variants.*.sku' => ['required', 'string', 'max:100'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0', 'max:999999'],
            'variants.*.low_stock_threshold' => ['required', 'integer', 'min:0', 'max:999999'],
            'variants.*.is_active' => ['boolean'],

            'images' => ['array'],
            'images.*' => ['nullable', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.*.mimes' => 'Ảnh phải là JPG, JPEG, PNG hoặc WebP.',
            'images.*.max' => 'Ảnh không được vượt quá 4MB.',
        ];
    }

    /**
     * Register custom validation for variant SKUs:
     *  - intra-request uniqueness (no two variants in the same request share a SKU)
     *  - cross-product uniqueness (SKU must not exist on any other product)
     * The current product's existing variants are excluded from the check.
     */
    public function withValidator(Validator $validator): void
    {
        $product = $this->route('product');
        $productId = $product?->id;
        $existingVariantIds = $product ? $product->variants()->pluck('id')->all() : [];

        $validator->after(function (Validator $validator) use ($productId) {
            $variants = $this->input('variants', []);
            if (! is_array($variants)) {
                return;
            }

            $seenInRequest = [];
            foreach ($variants as $key => $variant) {
                if (! is_array($variant)) {
                    continue;
                }
                $sku = strtoupper(trim((string) ($variant['sku'] ?? '')));
                if ($sku === '') {
                    continue;
                }

                // Intra-request duplicate.
                if (in_array($sku, $seenInRequest, true)) {
                    $validator->errors()->add("variants.{$key}.sku", 'SKU này đã được dùng trong cùng một sản phẩm.');

                    continue;
                }
                $seenInRequest[] = $sku;

                // Cross-product uniqueness: exclude the current product's variants.
                $query = ProductVariant::query()->where('sku', $sku);
                if ($productId) {
                    $query->where('product_id', '!=', $productId);
                }
                if ($query->exists()) {
                    $validator->errors()->add("variants.{$key}.sku", 'SKU này đã tồn tại ở sản phẩm khác.');
                }
            }
        });
    }
}
