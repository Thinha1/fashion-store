<?php

namespace App\Http\Requests\Admin;

class ProductAiExcelRequest extends BaseAdminRequest
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
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn file Excel (.xlsx).',
            'file.mimes' => 'Chỉ nhận file Excel định dạng .xlsx.',
            'file.max' => 'File Excel tối đa 5 MB.',
        ];
    }
}
