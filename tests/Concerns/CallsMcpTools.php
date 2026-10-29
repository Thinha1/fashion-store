<?php

namespace Tests\Concerns;

use App\Models\McpToken;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Testing\TestResponse;

/**
 * Talking to the shop's MCP server (/ai/shop) with a token for a fresh role.
 */
trait CallsMcpTools
{
    /** A token for a fresh role holding exactly `$codes`. */
    private function tokenFor(array $codes): string
    {
        return McpToken::issue($this->roleWith($codes), 30)[1];
    }

    private function roleWith(array $codes): Role
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->whereIn('code', $codes)->pluck('id'));

        return $role;
    }

    private function rpc(?string $token, string $method, array $params = []): TestResponse
    {
        return $this->withHeaders(array_filter([
            'Authorization' => $token === null ? null : 'Bearer '.$token,
            'Accept' => 'application/json, text/event-stream',
        ]))->postJson('/ai/shop', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params ?: (object) []]);
    }

    private function callTool(string $token, string $tool, array $arguments = []): array
    {
        return $this->rpc($token, 'tools/call', ['name' => $tool, 'arguments' => (object) $arguments])->assertOk()->json('result');
    }

    /** @return list<string> */
    private function toolNames(string $token): array
    {
        return collect($this->rpc($token, 'tools/list')->assertOk()->json('result.tools'))->pluck('name')->sort()->values()->all();
    }
}
