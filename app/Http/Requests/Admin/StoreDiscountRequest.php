<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDiscountRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'products.manage';
    }

    protected function prepareForValidation(): void
    {
        // The form always submits scope=variant (this controller only handles
        // variant-scoped discounts). If the client omits it, default it.
        if ($this->input('scope') === null) {
            $this->merge(['scope' => 'variant']);
        }
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        $discount = $this->route('discount');

        return [
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'scope' => ['required', Rule::in(['variant'])],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // scope=variant must NOT have a code (code is for scope=order coupons).
            if ($this->input('scope') === 'variant' && $this->filled('code')) {
                $validator->errors()->add('code', 'Giảm giá theo biến thể không được dùng mã giảm giá.');
            }

            // For percent discounts, the value must be between 0 and 100.
            if ($this->input('discount_type') === 'percent') {
                $value = (float) $this->input('discount_value', 0);
                if ($value > 100) {
                    $validator->errors()->add('discount_value', 'Giảm giá phần trăm không được vượt quá 100.');
                }
            }
        });
    }
}
