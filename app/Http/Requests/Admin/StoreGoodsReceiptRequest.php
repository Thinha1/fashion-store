<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGoodsReceiptRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'inventory.manage';
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        $receipt = $this->route('goodsReceipt');
        $isUpdate = $receipt !== null;

        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('items', []);
            if (! is_array($items)) {
                return;
            }

            $seen = [];
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $variantId = (int) ($item['product_variant_id'] ?? 0);
                if ($variantId === 0) {
                    continue;
                }
                if (isset($seen[$variantId])) {
                    $validator->errors()->add("items.{$index}.product_variant_id", 'Biến thể này đã được thêm vào phiếu nhập.');

                    continue;
                }
                $seen[$variantId] = true;
            }
        });
    }
}
