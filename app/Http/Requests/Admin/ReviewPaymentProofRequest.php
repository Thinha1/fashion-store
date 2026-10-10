<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ReviewPaymentProofRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'payments.manage';
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => [Rule::requiredIf($this->input('decision') === 'reject'), 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Vui lòng nhập lý do từ chối để khách biết cần làm gì.',
        ];
    }
}
