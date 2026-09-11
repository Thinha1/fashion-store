<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base class for admin CRUD form requests.
 *
 * The route-level `permission` middleware already enforces access for
 * read/create; each concrete subclass should override `authorize()` to
 * check the appropriate update/delete permission.
 */
abstract class BaseAdminRequest extends FormRequest
{
    /**
     * Permission code required to perform this request.
     */
    abstract public function permissionCode(): string;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission($this->permissionCode()) ?? false;
    }
}
