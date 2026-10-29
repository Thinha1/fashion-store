<?php

namespace App\Console\Commands;

use App\Models\McpToken;
use App\Models\Role;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Issues or revokes the bearer tokens of the MCP server (/ai/shop). A token
 * stands for a role; what it can read follows that role's permissions, and it
 * can only change data when issued with --write.
 */
#[Signature('mcp:token
    {role : Mã vai trò, ví dụ admin hoặc staff}
    {--days=30 : Số ngày token còn hiệu lực}
    {--name= : Tên gợi nhớ của token}
    {--write : Cho phép token làm việc thay đổi dữ liệu (xác nhận đơn, soạn phiếu nhập nháp, tạo voucher, giảm giá hàng loạt); mặc định chỉ đọc}
    {--revoke : Thu hồi mọi token còn hiệu lực của vai trò thay vì tạo mới}')]
#[Description('Tạo hoặc thu hồi token MCP theo vai trò')]
class McpTokenCommand extends Command
{
    /** Permissions that unlock at least one reading MCP tool (see App\Mcp\Tools). */
    private const READ_PERMISSIONS = ['dashboard.view', 'inventory.manage', 'orders.view', 'orders.manage'];

    /** Permissions that unlock a tool changing data, for a --write token. */
    private const WRITE_PERMISSIONS = ['orders.manage', 'inventory.manage', 'discounts.manage'];

    public function handle(AuditLogger $audit): int
    {
        $role = Role::query()->with('permissions')->where('code', $this->argument('role'))->first();

        if ($role === null) {
            $this->error('Không có vai trò "'.$this->argument('role').'". Các vai trò: '.Role::query()->orderBy('code')->pluck('code')->implode(', ').'.');

            return self::FAILURE;
        }

        return $this->option('revoke') ? $this->revoke($role, $audit) : $this->issue($role, $audit);
    }

    private function issue(Role $role, AuditLogger $audit): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);

        if ($days === false) {
            $this->error('--days phải là số ngày từ 1 đến 365.');

            return self::FAILURE;
        }

        $held = fn (array $codes): array => array_values(array_filter($codes, fn (string $code): bool => $role->hasPermission($code)));
        $canWrite = (bool) $this->option('write');
        $permissions = $held(self::READ_PERMISSIONS);
        $writable = $held(self::WRITE_PERMISSIONS);

        if ($canWrite && $writable === []) {
            $this->error("Vai trò \"{$role->name}\" không có quyền nào để ghi qua MCP (cần một trong: ".implode(', ', self::WRITE_PERMISSIONS).').');

            return self::FAILURE;
        }

        if (! $canWrite && $permissions === []) {
            $this->error("Vai trò \"{$role->name}\" không có quyền nào dùng được qua MCP (cần một trong: ".implode(', ', self::READ_PERMISSIONS).'; việc tạo voucher và giảm giá cần token --write).');

            return self::FAILURE;
        }

        [$token, $plain] = McpToken::issue($role, $days, $this->option('name'), $canWrite);
        $audit->record('mcp_token.created', $token, new: ['role' => $role->code, 'name' => $token->name, 'can_write' => $canWrite, 'expires_at' => $token->expires_at->toDateTimeString()]);

        $this->info("Đã tạo token \"{$token->name}\" cho vai trò {$role->name}, hết hạn {$token->expires_at->format('H:i d/m/Y')}.");
        $this->line('Quyền dùng được qua MCP: '.implode(', ', array_values(array_unique([...$permissions, ...($canWrite ? $writable : [])]))).'.');
        $this->line($canWrite
            ? 'Token này CÓ quyền ghi (xác nhận đơn, soạn phiếu nhập nháp, tạo voucher, giảm giá) trong phạm vi: '.implode(', ', $writable).'.'
            : 'Token chỉ đọc. Thêm --write nếu muốn token làm được việc thay đổi dữ liệu.');
        $this->newLine();
        $this->line($plain);
        $this->newLine();
        $this->warn('Token chỉ hiện một lần. Endpoint: '.rtrim((string) config('app.url'), '/').'/ai/shop');

        return self::SUCCESS;
    }

    private function revoke(Role $role, AuditLogger $audit): int
    {
        $tokens = McpToken::query()->usable()->where('role_id', $role->id)->get();

        foreach ($tokens as $token) {
            $token->forceFill(['revoked_at' => now()])->save();
            $audit->record('mcp_token.revoked', $token, old: ['role' => $role->code, 'name' => $token->name]);
        }

        $this->info("Đã thu hồi {$tokens->count()} token của vai trò {$role->name}.");

        return self::SUCCESS;
    }
}
