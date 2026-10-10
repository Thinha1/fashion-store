<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveAddressRequest extends FormRequest
{
    /**
     * Ownership of an existing address is checked by AddressPolicy in the
     * controller; any signed-in customer may add one.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^(0|\+84)[0-9]{9,10}$/'],
            'province_name' => ['required', 'string', 'max:255'],
            'district_name' => ['required', 'string', 'max:255'],
            'ward_name' => ['required', 'string', 'max:255'],
            'address_line' => ['required', 'string', 'max:500'],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Số điện thoại không hợp lệ (ví dụ 0901234567).',
        ];
    }
}
