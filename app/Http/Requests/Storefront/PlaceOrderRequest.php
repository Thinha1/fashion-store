<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `address_id` is one of the customer's saved addresses, or "new" with the
     * address fields filled in (that address is then saved to the book).
     * Money is never accepted from the form — PlaceOrder recomputes it.
     *
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        $isNewAddress = $this->input('address_id') === 'new';

        return [
            'address_id' => [
                'required',
                Rule::when(! $isNewAddress, [
                    'integer',
                    Rule::exists('addresses', 'id')->where('user_id', $this->user()->id),
                ]),
            ],
            'recipient_name' => [Rule::requiredIf($isNewAddress), 'nullable', 'string', 'max:255'],
            'phone' => [Rule::requiredIf($isNewAddress), 'nullable', 'string', 'max:20', 'regex:/^(0|\+84)[0-9]{9,10}$/'],
            'province_name' => [Rule::requiredIf($isNewAddress), 'nullable', 'string', 'max:255'],
            'district_name' => [Rule::requiredIf($isNewAddress), 'nullable', 'string', 'max:255'],
            'ward_name' => [Rule::requiredIf($isNewAddress), 'nullable', 'string', 'max:255'],
            'address_line' => [Rule::requiredIf($isNewAddress), 'nullable', 'string', 'max:500'],
            'label' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['required', Rule::in(['cod'])],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'customer_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address_id.required' => 'Vui lòng chọn địa chỉ giao hàng.',
            'address_id.exists' => 'Địa chỉ giao hàng không hợp lệ.',
            'phone.regex' => 'Số điện thoại không hợp lệ (ví dụ 0901234567).',
            'payment_method.in' => 'Phương thức thanh toán chưa được hỗ trợ.',
        ];
    }
}
