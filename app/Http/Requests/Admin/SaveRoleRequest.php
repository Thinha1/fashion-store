<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Creating and editing a role at /admin/vai-tro.
 */
class SaveRoleRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'roles.manage';
    }

    protected function prepareForValidation(): void
    {
        // The code is derived from the name on create and never changes.
        if ($this->route('role') === null) {
            $this->merge(['code' => Str::slug((string) $this->input('name'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role instanceof Role ? $role->id : null)],
            'code' => [$role ? 'prohibited' : 'required', 'string', 'max:60', Rule::unique('roles', 'code')],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::exists('permissions', 'code')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Đã có vai trò cùng tên.',
            'code.unique' => 'Tên này trùng mã với một vai trò khác, hãy đặt tên khác.',
            'code.required' => 'Tên vai trò cần có chữ hoặc số.',
        ];
    }

    /**
     * @return list<string>
     */
    public function permissionCodes(): array
    {
        return array_values(array_unique($this->validated('permissions') ?? []));
    }
}
