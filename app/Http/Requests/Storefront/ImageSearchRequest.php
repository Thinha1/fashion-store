<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class ImageSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Hãy chọn một ảnh để tìm.',
            'image.image' => 'Tệp tải lên phải là ảnh.',
            'image.mimes' => 'Ảnh phải là JPG, PNG hoặc WebP.',
            'image.max' => 'Ảnh không được vượt quá 5MB.',
        ];
    }
}
