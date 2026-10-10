<?php

namespace App\Http\Requests\Storefront;

use App\Services\Cart\ShoppingCart;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    /**
     * Ownership is checked by CartItemPolicy in the controller.
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
            'quantity' => ['required', 'integer', 'min:1', 'max:'.ShoppingCart::MAX_LINE_QUANTITY],
        ];
    }
}
