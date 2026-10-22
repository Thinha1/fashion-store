<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The chat widget resends the full conversation (text + the original image)
 * on every turn, since the AI has no memory between calls — see
 * spec-agent-dang-san-pham.md. Limits here exist purely to bound cost/abuse,
 * not to model the conversation as a stored resource.
 */
class ProductAiAssistRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'products.manage';
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        $maxMessages = max(1, (int) config('services.ai.max_history_messages'));

        return [
            'messages' => ['required', 'array', 'min:1', 'max:'.$maxMessages],
            'messages.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'messages.*.content' => ['required', 'array', 'min:1'],
            'messages.*.content.*.type' => ['required', Rule::in(['text', 'image'])],
            'messages.*.content.*.text' => ['required_if:messages.*.content.*.type,text', 'nullable', 'string', 'max:4000'],
            'messages.*.content.*.media_type' => ['required_if:messages.*.content.*.type,image', 'nullable', Rule::in(['image/jpeg', 'image/png', 'image/webp'])],
            'messages.*.content.*.data' => ['required_if:messages.*.content.*.type,image', 'nullable', 'string'],
            // Snapshot of the product form on screen (describeProductForm in
            // product-ai-chat.js) — see ProductFormContextDescriber.
            'form_context' => ['nullable', 'array'],
            'form_context.mode' => ['nullable', Rule::in(['create', 'edit'])],
            'form_context.name' => ['nullable', 'string', 'max:255'],
            'form_context.price' => ['nullable', 'string', 'max:30'],
            'form_context.category' => ['nullable', 'string', 'max:255'],
            'form_context.brand' => ['nullable', 'string', 'max:255'],
            'form_context.status' => ['nullable', Rule::in(['active', 'archived'])],
            'form_context.description' => ['nullable', 'string', 'max:1000'],
            'form_context.variants' => ['nullable', 'array', 'max:60'],
            'form_context.variants.*.size' => ['nullable', 'string', 'max:20'],
            'form_context.variants.*.color' => ['nullable', 'string', 'max:50'],
            'form_context.variants.*.sku' => ['nullable', 'string', 'max:100'],
            'form_context.variants.*.price' => ['nullable', 'string', 'max:30'],
            'form_context.variants.*.stock' => ['nullable', 'string', 'max:20'],
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
            'messages.*.content.*.data.required_if' => 'Thiếu dữ liệu ảnh đính kèm.',
        ];
    }

    /**
     * Bound the decoded size of every embedded image — Laravel has no
     * built-in rule for the size of a base64 string once decoded.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $maxBytes = max(1, (int) config('services.ai.max_image_kb')) * 1024;

            foreach ((array) $this->input('messages', []) as $messageKey => $message) {
                foreach ((array) ($message['content'] ?? []) as $blockKey => $block) {
                    if (($block['type'] ?? null) !== 'image') {
                        continue;
                    }

                    $data = (string) ($block['data'] ?? '');
                    $decoded = base64_decode($data, true);

                    if ($decoded === false) {
                        $validator->errors()->add("messages.{$messageKey}.content.{$blockKey}.data", 'Ảnh đính kèm không hợp lệ.');

                        continue;
                    }

                    if (strlen($decoded) > $maxBytes) {
                        $validator->errors()->add("messages.{$messageKey}.content.{$blockKey}.data", 'Ảnh đính kèm vượt quá dung lượng cho phép.');
                    }
                }
            }
        });
    }
}
