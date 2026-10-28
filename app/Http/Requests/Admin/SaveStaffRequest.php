<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Creating and editing a staff account at /admin/nhan-vien. The password is
 * required on create and optional on edit (blank keeps the current one).
 */
class SaveStaffRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'staff.manage';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $staff = $this->route('staff');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff instanceof User ? $staff->id : null)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+ .-]+$/'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'status' => ['required', Rule::in(['active', 'locked'])],
            'password' => [$staff ? 'nullable' : 'required', 'string', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email này đã có tài khoản.',
            'phone.regex' => 'Số điện thoại chỉ gồm chữ số.',
        ];
    }
}
