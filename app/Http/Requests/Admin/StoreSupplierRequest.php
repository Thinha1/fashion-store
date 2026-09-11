<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'suppliers.manage';
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-()]{6,20}$/'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'tax_code' => ['nullable', 'string', 'max:50', Rule::unique('suppliers', 'tax_code')->ignore($supplier?->id)],
            'is_active' => ['boolean'],
        ];
    }
}
