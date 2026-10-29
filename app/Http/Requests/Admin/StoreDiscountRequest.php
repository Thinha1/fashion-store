<?php

namespace App\Http\Requests\Admin;

use App\Models\Discount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One form for every discount kind: a variant discount (scope=variant) or a
 * voucher (scope=order lowers the subtotal, scope=shipping the shipping fee).
 * A voucher needs a code unless it applies automatically.
 */
class StoreDiscountRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'discounts.manage';
    }

    protected function prepareForValidation(): void
    {
        $existing = $this->route('discount');

        $this->merge([
            // The kind is chosen when the discount is created and kept after
            // that; the Excel import and older clients omit it (variant).
            'scope' => $existing instanceof Discount ? $existing->scope : ($this->input('scope') ?? 'variant'),
            // Codes are stored upper-case, which is how checkout looks them up.
            'code' => $this->filled('code') ? mb_strtoupper(trim((string) $this->input('code'))) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        $discount = $this->route('discount');
        $voucher = $this->isVoucher();

        return [
            'scope' => ['required', Rule::in(array_keys(Discount::SCOPE_LABELS))],
            'product_variant_id' => [Rule::requiredIf(! $voucher), 'nullable', 'integer', Rule::exists('product_variants', 'id')],
            'name' => [Rule::requiredIf($voucher), 'nullable', 'string', 'max:120'],
            'code' => [
                Rule::requiredIf($voucher && ! $this->boolean('is_auto_apply')), 'nullable', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('discounts', 'code')->ignore($discount instanceof Discount ? $discount->id : null),
            ],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
            'is_auto_apply' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Voucher không tự động áp dụng cần có mã để khách nhập.',
            'code.regex' => 'Mã chỉ gồm chữ không dấu, số, gạch ngang hoặc gạch dưới.',
            'code.unique' => 'Mã này đã được dùng cho một giảm giá khác.',
            'name.required' => 'Hãy đặt tên cho voucher để khách biết ưu đãi là gì.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // A variant discount lowers one variant's price: no code, no auto-apply.
            if (! $this->isVoucher() && $this->filled('code')) {
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

    /**
     * The validated attributes for the discount's kind; fields of the other
     * kind are cleared so a voucher never points at a variant and a variant
     * discount never carries voucher rules.
     *
     * @return array<string, mixed>
     */
    public function discountData(): array
    {
        $kindFields = $this->isVoucher()
            ? ['product_variant_id' => null, 'is_auto_apply' => $this->boolean('is_auto_apply')]
            : array_fill_keys(['code', 'name', 'min_order_amount', 'usage_limit', 'usage_limit_per_customer'], null) + ['is_auto_apply' => false];

        return array_merge($this->validated(), $kindFields, ['is_active' => $this->boolean('is_active', true)]);
    }

    private function isVoucher(): bool
    {
        return in_array($this->input('scope'), Discount::VOUCHER_SCOPES, true);
    }
}
