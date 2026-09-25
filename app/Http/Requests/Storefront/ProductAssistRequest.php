<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The customer-facing shopping-assist widget resends the full conversation
 * on every turn, since the AI has no memory between calls. Unlike the admin
 * product-authoring assistant, this is guest-callable (no permission check)
 * and text-only — no image attachments in this first version.
 */
class ProductAssistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        $maxMessages = max(1, (int) config('services.ai.shopping_assist_max_history_messages'));

        return [
            'messages' => ['required', 'array', 'min:1', 'max:'.$maxMessages],
            'messages.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'messages.*.content' => ['required', 'string', 'max:500'],
            // Sent automatically by the widget when the customer opens chat
            // on a product detail page (see layouts/app.blade.php's
            // `data-current-product-id`) — just an id to look up server-side
            // for prompt context, never trusted content itself.
            'context_product_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'messages.required' => 'Thiếu nội dung hội thoại gửi cho AI.',
            'messages.max' => 'Hội thoại quá dài, hãy bắt đầu cuộc trò chuyện mới.',
            'messages.*.content.max' => 'Tin nhắn quá dài, vui lòng viết ngắn gọn hơn.',
        ];
    }
}
