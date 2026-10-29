<?php

namespace App\Services\Staff;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The safety rules for changing roles and staff accounts
 * (/admin/vai-tro, /admin/nhan-vien), so nobody can lock the shop out of its
 * own admin area or hand out more access than they hold:
 *
 * - "admin" (always every permission) and "customer" (never any) are fixed;
 * - you only grant permissions, or assign roles, that you hold yourself;
 * - you cannot lock yourself, change your own role or take roles.manage /
 *   staff.manage away from your own role;
 * - at least one active account keeps roles.manage.
 *
 * Each check returns the Vietnamese reason it fails, or null when allowed.
 */
class StaffAccess
{
    /** Roles whose permissions the screens never change. */
    public const LOCKED_ROLES = ['admin', 'customer'];

    /** What you must keep on your own role while editing it. */
    private const SELF_KEPT = ['roles.manage', 'staff.manage'];

    public function isLocked(Role $role): bool
    {
        return in_array($role->code, self::LOCKED_ROLES, true);
    }

    /**
     * Roles a staff account can be given: every role but "customer".
     *
     * @return Collection<int, Role>
     */
    public function staffRoles(): Collection
    {
        return Role::query()->where('code', '!=', 'customer')->with('permissions')->orderBy('name')->get();
    }

    /**
     * @param  list<string>  $codes  the permission codes the role would hold
     */
    public function checkRolePermissions(User $actor, ?Role $role, array $codes): ?string
    {
        if ($role !== null && $this->isLocked($role)) {
            return 'Vai trò "'.$role->name.'" là vai trò hệ thống, không đổi quyền được.';
        }

        // What the role already has may stay; only new grants are limited.
        $beyond = array_values(array_filter($codes, fn (string $code): bool => ! $actor->hasPermission($code)
            && ! ($role?->hasPermission($code) ?? false)));
        if ($beyond !== []) {
            return 'Bạn không thể cấp quyền mà chính bạn không có: '.implode(', ', $this->names($beyond)).'.';
        }

        if ($role !== null && $actor->role_id === $role->id) {
            $dropped = array_values(array_diff(self::SELF_KEPT, $codes));
            $dropped = array_values(array_filter($dropped, fn (string $code): bool => $role->hasPermission($code)));
            if ($dropped !== []) {
                return 'Bạn không thể tự gỡ quyền '.implode(', ', $this->names($dropped)).' khỏi vai trò của chính mình.';
            }
        }

        if ($role !== null && $role->hasPermission('roles.manage') && ! in_array('roles.manage', $codes, true)
            && ! $this->anotherRoleManager(exceptRoleId: $role->id)) {
            return 'Phải còn ít nhất một tài khoản đang hoạt động có quyền phân quyền.';
        }

        return null;
    }

    public function checkRoleDeletion(Role $role): ?string
    {
        return match (true) {
            $this->isLocked($role) => 'Vai trò hệ thống không xóa được.',
            $role->users()->where('status', '!=', User::STATUS_SYSTEM)->exists() => 'Vai trò đang có tài khoản sử dụng. Hãy chuyển các tài khoản sang vai trò khác trước.',
            $role->users()->exists() => 'Vai trò đã được dùng qua MCP (tài khoản hệ thống "Claude (MCP)"), nên không xóa được. Hãy thu hồi token của vai trò này và bỏ quyền của vai trò thay vì xóa.',
            default => null,
        };
    }

    /**
     * Assigning $role and $status to $target (null target = a new account).
     */
    public function checkStaffChange(User $actor, ?User $target, Role $role, string $status): ?string
    {
        if ($role->code === 'customer') {
            return 'Tài khoản nhân viên không dùng vai trò khách hàng.';
        }

        $beyond = $role->permissions->pluck('code')->reject(fn (string $code): bool => $actor->hasPermission($code))->values()->all();
        if ($beyond !== [] && ($target === null || $target->role_id !== $role->id)) {
            return 'Bạn không thể gán vai trò có quyền mà chính bạn không có: '.implode(', ', $this->names($beyond)).'.';
        }

        if ($target === null) {
            return null;
        }

        if ($target->is($actor) && $target->role_id !== $role->id) {
            return 'Bạn không thể tự đổi vai trò của chính mình.';
        }

        if ($target->is($actor) && $status === 'locked') {
            return 'Bạn không thể tự khóa tài khoản của chính mình.';
        }

        $losesRoleManager = $target->hasPermission('roles.manage')
            && ($status === 'locked' || ! $role->hasPermission('roles.manage'));
        if ($losesRoleManager && ! $this->anotherRoleManager(exceptUserId: $target->id)) {
            return 'Phải còn ít nhất một tài khoản đang hoạt động có quyền phân quyền.';
        }

        return null;
    }

    /**
     * Whether some active account other than the excluded one(s) holds roles.manage.
     */
    private function anotherRoleManager(?int $exceptRoleId = null, ?int $exceptUserId = null): bool
    {
        return User::query()
            ->whereNotIn('status', ['locked', User::STATUS_SYSTEM])
            ->when($exceptRoleId, fn ($query) => $query->where('role_id', '!=', $exceptRoleId))
            ->when($exceptUserId, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->whereHas('role.permissions', fn ($query) => $query->where('code', 'roles.manage'))
            ->exists();
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function names(array $codes): array
    {
        $names = Permission::query()->whereIn('code', $codes)->pluck('name', 'code');

        return array_map(fn (string $code): string => '"'.($names[$code] ?? $code).'"', $codes);
    }
}
