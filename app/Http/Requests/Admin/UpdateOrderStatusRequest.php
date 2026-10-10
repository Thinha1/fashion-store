<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'orders.manage';
    }

    /**
     * Whether the move is allowed from the order's current status is checked
     * by ChangeOrderStatus under a row lock, not here.
     *
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_keys(Order::TRANSITION_LABELS))],
            'note' => [Rule::requiredIf($this->input('status') === 'cancelled'), 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => 'Vui lòng nhập lý do hủy đơn.',
        ];
    }
}
