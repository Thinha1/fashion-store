<?php

namespace App\Http\Requests\Admin;

class ProductAiPdfRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'products.manage';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn file PDF.',
            'file.mimes' => 'Chỉ nhận file định dạng .pdf.',
            'file.max' => 'File PDF tối đa 10 MB.',
        ];
    }
}
