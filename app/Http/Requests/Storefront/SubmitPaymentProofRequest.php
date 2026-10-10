<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPaymentProofRequest extends FormRequest
{
    /**
     * Ownership is checked by OrderPolicy in the controller.
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
            'transaction_code' => ['required', 'string', 'max:100'],
            'receipt' => ['required', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transaction_code.required' => 'Vui lòng nhập mã giao dịch trong biên lai chuyển khoản.',
            'receipt.required' => 'Vui lòng chọn ảnh chụp biên lai chuyển khoản.',
            'receipt.image' => 'Biên lai phải là file ảnh.',
            'receipt.mimes' => 'Ảnh phải là JPG, JPEG, PNG hoặc WebP.',
            'receipt.max' => 'Ảnh tối đa 4 MB.',
        ];
    }
}
