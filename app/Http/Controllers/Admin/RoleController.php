<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit\AuditLogger;
use App\Services\Staff\StaffAccess;
use App\Support\PermissionGroups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Roles and the permissions each one grants. The safety rules (fixed system
 * roles, no granting beyond your own access, someone always keeps
 * roles.manage) live in StaffAccess.
 */
class RoleController extends Controller
{
    public function __construct(private readonly StaffAccess $access) {}

    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderBy('id')->get(),
            'permissionTotal' => Permission::query()->count(),
            'access' => $this->access,
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', $this->formData(new Role));
    }

    public function store(SaveRoleRequest $request): RedirectResponse
    {
        $codes = $request->permissionCodes();
        if ($problem = $this->access->checkRolePermissions($request->user(), null, $codes)) {
            return back()->withInput()->with('error', $problem);
        }

        $role = DB::transaction(function () use ($request, $codes): Role {
            $role = Role::query()->create($request->safe()->only(['name', 'code', 'description']) + [
                'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
            ]);
            $role->permissions()->sync(Permission::query()->whereIn('code', $codes)->pluck('id'));
            $this->logPermissions($role, [], $codes);

            return $role;
        });

        return redirect()->route('admin.roles.index')->with('status', "Đã tạo vai trò \"{$role->name}\".");
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', $this->formData($role));
    }

    public function update(SaveRoleRequest $request, Role $role): RedirectResponse
    {
        $codes = $request->permissionCodes();
        $before = $role->permissions->pluck('code')->sort()->values()->all();
        // A system role keeps its permissions; only its name and description change.
        $changesPermissions = ! $this->access->isLocked($role) || $request->has('permissions');
        if ($changesPermissions && ($problem = $this->access->checkRolePermissions($request->user(), $role, $codes))) {
            return back()->withInput()->with('error', $problem);
        }

        DB::transaction(function () use ($request, $role, $codes, $before, $changesPermissions): void {
            $role->update($request->safe()->only(['name', 'description']) + ['updated_by' => $request->user()->id]);
            if ($changesPermissions) {
                $role->permissions()->sync(Permission::query()->whereIn('code', $codes)->pluck('id'));
                $this->logPermissions($role, $before, $codes);
            }
        });

        return redirect()->route('admin.roles.index')->with('status', "Đã lưu vai trò \"{$role->name}\".");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($problem = $this->access->checkRoleDeletion($role)) {
            return back()->with('error', $problem);
        }

        $before = $role->permissions->pluck('code')->sort()->values()->all();
        $role->delete();
        app(AuditLogger::class)->record('role.deleted', $role, ['name' => $role->name, 'code' => $role->code, 'permissions' => $before], null);

        return redirect()->route('admin.roles.index')->with('status', "Đã xóa vai trò \"{$role->name}\".");
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    private function logPermissions(Role $role, array $before, array $after): void
    {
        sort($after);
        if ($before === $after && $role->wasRecentlyCreated === false) {
            return;
        }

        app(AuditLogger::class)->record(
            $role->wasRecentlyCreated ? 'role.created' : 'role.permissions_changed',
            $role,
            $role->wasRecentlyCreated ? null : ['permissions' => $before],
            ['name' => $role->name, 'permissions' => $after],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Role $role): array
    {
        return [
            'role' => $role,
            'groups' => PermissionGroups::grouped(),
            'locked' => $role->exists && $this->access->isLocked($role),
            'granted' => $role->exists ? $role->permissions->pluck('code')->all() : [],
        ];
    }
}
