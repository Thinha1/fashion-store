<?php

namespace Tests\Feature\Admin;

use App\Models\AiSetting;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSettingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function config(array $attributes = []): AiSetting
    {
        return AiSetting::query()->create(['name' => 'Cấu hình', ...$attributes]);
    }

    public function test_staff_without_settings_permission_cannot_view_the_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.settings.ai.edit'))
            ->assertForbidden();
    }

    public function test_admin_can_view_the_settings_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.ai.edit'))
            ->assertOk()
            ->assertSee('Cấu hình AI');
    }

    public function test_page_lists_every_saved_configuration_with_its_base_url_and_model(): void
    {
        $this->config(['name' => 'DeepSeek', 'endpoint' => 'https://api.deepseek.test', 'model' => 'deepseek-x', 'is_primary' => true]);
        $this->config(['name' => 'Qwen nội bộ', 'endpoint' => 'https://qwen.test/v1', 'model' => 'qwen-y']);

        $this->actingAs($this->admin())
            ->get(route('admin.settings.ai.edit'))
            ->assertOk()
            ->assertSeeInOrder(['DeepSeek', 'https://api.deepseek.test', 'deepseek-x', 'Đang dùng'])
            ->assertSee('Qwen nội bộ')
            ->assertSee('https://qwen.test/v1')
            ->assertSee('qwen-y');
    }

    public function test_page_warns_when_no_configuration_is_primary(): void
    {
        $this->config();

        $this->actingAs($this->admin())
            ->get(route('admin.settings.ai.edit'))
            ->assertSee('Chưa có cấu hình nào được chọn làm chính');
    }

    public function test_api_key_is_never_returned_as_plaintext_on_the_page(): void
    {
        $this->config(['api_key' => 'sk-super-secret-ab12', 'is_primary' => true]);

        $this->actingAs($this->admin())
            ->get(route('admin.settings.ai.edit'))
            ->assertOk()
            ->assertDontSee('sk-super-secret-ab12')
            ->assertSee('ab12', false); // masked tail is still fine to show — sanity-check the mask ran
    }

    public function test_edit_mode_loads_the_chosen_configuration_into_the_form(): void
    {
        $setting = $this->config(['name' => 'Bản sửa', 'model' => 'model-sua']);

        $this->actingAs($this->admin())
            ->get(route('admin.settings.ai.edit', ['sua' => $setting->id]))
            ->assertOk()
            ->assertSee('Sửa cấu hình: Bản sửa')
            ->assertSee(route('admin.settings.ai.update', $setting), false);
    }

    public function test_admin_can_add_a_configuration(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.ai.store'), [
            'name' => 'DeepSeek',
            'endpoint' => 'https://ai.example.test/v1',
            'model' => 'qwen-test',
            'max_tokens' => 2048,
            'api_key' => 'sk-new',
        ])->assertRedirect(route('admin.settings.ai.edit'));

        $this->assertDatabaseHas('ai_settings', [
            'name' => 'DeepSeek',
            'endpoint' => 'https://ai.example.test/v1',
            'model' => 'qwen-test',
            'max_tokens' => 2048,
        ]);
        $this->assertSame('sk-new', AiSetting::query()->first()->api_key);
    }

    public function test_the_name_is_required(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.ai.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('ai_settings', 0);
    }

    public function test_the_first_configuration_becomes_primary_and_later_ones_do_not(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.settings.ai.store'), ['name' => 'Đầu tiên']);
        $this->actingAs($admin)->post(route('admin.settings.ai.store'), ['name' => 'Thứ hai']);

        $this->assertTrue(AiSetting::query()->where('name', 'Đầu tiên')->first()->is_primary);
        $this->assertFalse(AiSetting::query()->where('name', 'Thứ hai')->first()->is_primary);
    }

    public function test_making_a_configuration_primary_demotes_the_previous_one(): void
    {
        $old = $this->config(['name' => 'Cũ', 'is_primary' => true]);
        $new = $this->config(['name' => 'Mới']);

        $this->actingAs($this->admin())
            ->post(route('admin.settings.ai.primary', $new))
            ->assertRedirect(route('admin.settings.ai.edit'));

        $this->assertFalse($old->fresh()->is_primary);
        $this->assertTrue($new->fresh()->is_primary);
        $this->assertSame(1, AiSetting::query()->where('is_primary', true)->count());
        $this->assertNotNull(AuditLog::query()->where('action', 'ai_settings.primary_changed')->first());
    }

    public function test_admin_can_edit_a_configuration_without_touching_the_others(): void
    {
        $target = $this->config(['name' => 'A', 'model' => 'm-a']);
        $other = $this->config(['name' => 'B', 'model' => 'm-b']);

        $this->actingAs($this->admin())->put(route('admin.settings.ai.update', $target), [
            'name' => 'A đã sửa',
            'model' => 'm-a2',
        ])->assertRedirect(route('admin.settings.ai.edit'));

        $this->assertSame('m-a2', $target->fresh()->model);
        $this->assertSame('A đã sửa', $target->fresh()->name);
        $this->assertSame('m-b', $other->fresh()->model);
    }

    public function test_leaving_api_key_blank_keeps_the_existing_key(): void
    {
        $setting = $this->config(['api_key' => 'sk-original-ab12']);

        $this->actingAs($this->admin())->put(route('admin.settings.ai.update', $setting), [
            'name' => 'Cấu hình',
            'endpoint' => 'https://ai.example.test/v1',
        ]);

        $this->assertSame('sk-original-ab12', $setting->fresh()->api_key);
    }

    public function test_a_new_api_key_replaces_the_old_one(): void
    {
        $setting = $this->config(['api_key' => 'sk-old']);

        $this->actingAs($this->admin())->put(route('admin.settings.ai.update', $setting), [
            'name' => 'Cấu hình',
            'api_key' => 'sk-new',
        ]);

        $this->assertSame('sk-new', $setting->fresh()->api_key);
    }

    public function test_clear_api_key_checkbox_removes_the_key(): void
    {
        $setting = $this->config(['api_key' => 'sk-old']);

        $this->actingAs($this->admin())->put(route('admin.settings.ai.update', $setting), [
            'name' => 'Cấu hình',
            'clear_api_key' => '1',
        ]);

        $this->assertNull($setting->fresh()->api_key);
    }

    public function test_saving_records_an_audit_log_without_the_key_value(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.ai.store'), [
            'name' => 'Cấu hình',
            'endpoint' => 'https://ai.example.test/v1',
            'api_key' => 'sk-brand-new',
        ]);

        $log = AuditLog::query()->where('action', 'ai_settings.created')->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->new_values['api_key_changed']);
        $this->assertStringNotContainsString('sk-brand-new', json_encode($log->new_values));
    }

    public function test_leaving_endpoint_blank_falls_back_to_the_env_default(): void
    {
        config(['services.ai.openai_compatible.endpoint' => 'https://fallback.example.test/v1']);
        $setting = $this->config(['endpoint' => 'https://custom.example.test/v1']);

        $this->actingAs($this->admin())->put(route('admin.settings.ai.update', $setting), [
            'name' => 'Cấu hình',
            'endpoint' => '',
        ]);

        $this->assertNull($setting->fresh()->endpoint);
    }

    public function test_a_non_primary_configuration_can_be_deleted(): void
    {
        $this->config(['is_primary' => true]);
        $spare = $this->config(['name' => 'Dự phòng']);

        $this->actingAs($this->admin())
            ->delete(route('admin.settings.ai.destroy', $spare))
            ->assertRedirect(route('admin.settings.ai.edit'));

        $this->assertModelMissing($spare);
    }

    public function test_the_primary_configuration_cannot_be_deleted(): void
    {
        $primary = $this->config(['is_primary' => true]);

        $this->actingAs($this->admin())
            ->delete(route('admin.settings.ai.destroy', $primary))
            ->assertRedirect(route('admin.settings.ai.edit'))
            ->assertSessionHasErrors('ai_setting');

        $this->assertModelExists($primary);
    }

    public function test_staff_without_settings_permission_cannot_change_configurations(): void
    {
        $setting = $this->config();
        $staff = User::factory()->create();

        $this->actingAs($staff)->post(route('admin.settings.ai.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($staff)->post(route('admin.settings.ai.primary', $setting))->assertForbidden();
        $this->actingAs($staff)->delete(route('admin.settings.ai.destroy', $setting))->assertForbidden();
        $this->assertFalse($setting->fresh()->is_primary);
        $this->assertModelExists($setting);
    }

    public function test_connection_test_reports_success_when_the_provider_responds(): void
    {
        Http::fake(['ai.example.test/*' => Http::response([
            'choices' => [['message' => ['content' => '{"ok": true}']]],
        ])]);

        $this->actingAs($this->admin())->postJson(route('admin.settings.ai.test'), [
            'endpoint' => 'https://ai.example.test/v1',
            'model' => 'qwen-test',
            'api_key' => 'sk-test',
        ])->assertOk()->assertJson(['ok' => true]);
    }

    public function test_connection_test_reports_failure_on_connection_error(): void
    {
        Http::fake(['ai.example.test/*' => Http::failedConnection()]);

        $this->actingAs($this->admin())->postJson(route('admin.settings.ai.test'), [
            'endpoint' => 'https://ai.example.test/v1',
        ])->assertOk()->assertJson(['ok' => false]);
    }

    public function test_connection_test_uses_the_key_of_the_configuration_being_edited_when_the_field_is_blank(): void
    {
        $this->config(['endpoint' => 'https://other.example.test/v1', 'api_key' => 'sk-primary', 'is_primary' => true]);
        $edited = $this->config(['endpoint' => 'https://ai.example.test/v1', 'api_key' => 'sk-edited']);
        Http::fake(['ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => '{"ok": true}']]]])]);

        $this->actingAs($this->admin())->postJson(route('admin.settings.ai.test'), [
            'setting_id' => $edited->id,
        ])->assertOk()->assertJson(['ok' => true]);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-edited'));
    }
}
