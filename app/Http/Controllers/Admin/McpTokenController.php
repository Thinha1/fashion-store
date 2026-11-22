<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\McpToken;
use App\Models\Role;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Issues and revokes MCP server tokens from the AI settings screen — the same
 * tokens `php artisan mcp:token` makes. A token reads what its role may read,
 * so nobody issues one for a role holding MCP permissions they lack.
 */
class McpTokenController extends Controller
{
    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'name' => ['nullable', 'string', 'max:120'],
        ], [
            'days.min' => 'Số ngày hiệu lực từ 1 đến 365.',
            'days.max' => 'Số ngày hiệu lực từ 1 đến 365.',
        ]);

        $role = Role::query()->with('permissions')->findOrFail($validated['role_id']);
        $permissions = McpToken::toolPermissions($role);
        $beyond = array_values(array_filter($permissions, fn (string $code): bool => ! $request->user()->hasPermission($code)));

        $problem = match (true) {
            $permissions === [] => 'Vai trò "'.$role->name.'" không có quyền nào dùng được qua MCP (cần một trong: '.implode(', ', McpToken::TOOL_PERMISSIONS).').',
            $beyond !== [] => 'Bạn không thể tạo token cho vai trò có quyền mà chính bạn không có: '.implode(', ', $beyond).'.',
            default => null,
        };
        if ($problem !== null) {
            return back()->withInput()->withErrors(['role_id' => $problem]);
        }

        [$token, $plain] = McpToken::issue($role, $validated['days'], $validated['name'] ?? null);
        $audit->record('mcp_token.created', $token, new: ['role' => $role->code, 'name' => $token->name, 'expires_at' => $token->expires_at->toDateTimeString()]);

        // Shown on the next page only; the database keeps just the hash.
        return redirect()->route('admin.settings.ai.edit')->with('mcp_token', [
            'plain' => $plain,
            'name' => $token->name,
            'role' => $role->name,
            'expires_at' => $token->expires_at->format('H:i d/m/Y'),
            'permissions' => $permissions,
        ]);
    }

    public function destroy(McpToken $mcpToken, AuditLogger $audit): RedirectResponse
    {
        if ($mcpToken->revoked_at === null) {
            $mcpToken->forceFill(['revoked_at' => now()])->save();
            $audit->record('mcp_token.revoked', $mcpToken, old: ['role' => $mcpToken->role?->code, 'name' => $mcpToken->name]);
        }

        return redirect()->route('admin.settings.ai.edit')
            ->with('status', 'Đã thu hồi token MCP "'.$mcpToken->name.'".');
    }
}
