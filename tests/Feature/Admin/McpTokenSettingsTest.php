<?php

namespace Tests\Feature\Admin;

use App\Models\McpToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * MCP tokens issued and revoked from the AI settings screen, the same tokens
 * `php artisan mcp:token` makes.
 */
#[Group('mcp')]
class McpTokenSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function roleWith(string ...$codes): Role
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->whereIn('code', $codes)->pluck('id'));

        return $role;
    }

    private function staffWith(string ...$codes): User
    {
        return User::factory()->create(['role_id' => $this->roleWith(...$codes)->id]);
    }

    public function test_the_ai_settings_page_lists_mcp_tokens_without_their_secret(): void
    {
        $role = $this->roleWith('orders.view');
        $role->update(['name' => 'Bán hàng ca sáng']);
        [$live] = McpToken::issue($role, 30, 'Token đang dùng');
        McpToken::issue($role, 30, 'Token đã thu hồi')[0]->forceFill(['revoked_at' => now()])->save();
        McpToken::issue($role, 30, 'Token hết hạn')[0]->forceFill(['expires_at' => now()->subDay()])->save();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.settings.ai.edit'))
            ->assertOk()
            ->assertSee('Token MCP')
            ->assertSeeInOrder(['Token đang dùng', 'Bán hàng ca sáng', 'Đang dùng'])
            ->assertSeeInOrder(['Token đã thu hồi', 'Đã thu hồi'])
            ->assertSeeInOrder(['Token hết hạn', 'Hết hạn'])
            ->assertDontSee($live->token_hash);
    }

    public function test_issuing_a_token_shows_it_once_and_logs_it(): void
    {
        $admin = User::factory()->admin()->create();
        $role = $this->roleWith('dashboard.view');

        $this->actingAs($admin)
            ->post(route('admin.settings.mcp-tokens.store'), ['role_id' => $role->id, 'days' => 7, 'name' => 'Claude của chủ shop'])
            ->assertRedirect(route('admin.settings.ai.edit'))
            ->assertSessionHasNoErrors();

        $token = McpToken::query()->sole();
        $this->assertSame('Claude của chủ shop', $token->name);
        $this->assertSame($role->id, $token->role_id);
        $this->assertSame(now()->addDays(7)->toDateString(), $token->expires_at->toDateString());

        $page = $this->actingAs($admin)->get(route('admin.settings.ai.edit'))->assertOk();
        $this->assertSame(1, preg_match('/fsm_[A-Za-z0-9]{48}/', $page->getContent(), $match));
        $this->assertTrue($token->is(McpToken::findUsable($match[0])));
        $this->assertDatabaseHas('audit_logs', ['action' => 'mcp_token.created', 'subject_id' => $token->id, 'actor_id' => $admin->id]);

        $this->actingAs($admin)->get(route('admin.settings.ai.edit'))->assertDontSee($match[0]);
    }

    public function test_a_token_needs_a_role_with_mcp_permissions_and_a_valid_lifetime(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.settings.mcp-tokens.store'), ['role_id' => $this->roleWith('catalog.manage')->id, 'days' => 30])
            ->assertSessionHasErrors('role_id');
        $this->actingAs($admin)
            ->post(route('admin.settings.mcp-tokens.store'), ['role_id' => $this->roleWith('orders.view')->id, 'days' => 400])
            ->assertSessionHasErrors('days');

        $this->assertSame(0, McpToken::query()->count());
    }

    public function test_nobody_issues_a_token_with_more_access_than_they_hold(): void
    {
        $staff = $this->staffWith('admin.access', 'settings.ai', 'orders.view');

        $this->actingAs($staff)
            ->post(route('admin.settings.mcp-tokens.store'), ['role_id' => $this->roleWith('orders.view', 'customers.view')->id, 'days' => 30])
            ->assertSessionHasErrors('role_id');
        $this->assertSame(0, McpToken::query()->count());

        $this->actingAs($staff)
            ->post(route('admin.settings.mcp-tokens.store'), ['role_id' => $this->roleWith('orders.view', 'catalog.manage')->id, 'days' => 30])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, McpToken::query()->count());
    }

    public function test_revoking_a_token_stops_it_and_logs_it(): void
    {
        $admin = User::factory()->admin()->create();
        [$token, $plain] = McpToken::issue($this->roleWith('dashboard.view'), 30);

        $this->actingAs($admin)
            ->delete(route('admin.settings.mcp-tokens.destroy', $token))
            ->assertRedirect(route('admin.settings.ai.edit'));

        $this->assertNotNull($token->fresh()->revoked_at);
        $this->assertNull(McpToken::findUsable($plain));
        $this->assertDatabaseHas('audit_logs', ['action' => 'mcp_token.revoked', 'subject_id' => $token->id, 'actor_id' => $admin->id]);
    }

    public function test_the_token_actions_need_the_ai_settings_permission(): void
    {
        $staff = $this->staffWith('admin.access', 'dashboard.view');
        $role = $this->roleWith('dashboard.view');
        [$token] = McpToken::issue($role, 30);

        $this->actingAs($staff)->post(route('admin.settings.mcp-tokens.store'), ['role_id' => $role->id, 'days' => 30])->assertForbidden();
        $this->actingAs($staff)->delete(route('admin.settings.mcp-tokens.destroy', $token))->assertForbidden();

        $this->assertSame(1, McpToken::query()->count());
        $this->assertNull($token->fresh()->revoked_at);
    }
}
