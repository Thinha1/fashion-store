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

    public function test_admin_can_save_endpoint_model_and_max_tokens(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.settings.ai.update'), [
            'endpoint' => 'https://ai.example.test/v1',
            'model' => 'qwen-test',
            'max_tokens' => 2048,
        ]);

        $response->assertRedirect(route('admin.settings.ai.edit'));
        $this->assertDatabaseHas('ai_settings', [
            'endpoint' => 'https://ai.example.test/v1',
            'model' => 'qwen-test',
            'max_tokens' => 2048,
        ]);
    }

    public function test_api_key_is_never_returned_as_plaintext_on_the_edit_page(): void
    {
        AiSetting::query()->create(['api_key' => 'sk-super-secret-ab12']);

        $this->actingAs($this->admin())
            ->get(route('admin.settings.ai.edit'))
            ->assertOk()
            ->assertDontSee('sk-super-secret-ab12')
            ->assertSee('ab12', false); // masked tail is still fine to show — sanity-check the mask ran
    }

    public function test_leaving_api_key_blank_keeps_the_existing_key(): void
    {
        AiSetting::query()->create(['api_key' => 'sk-original-ab12']);

        $this->actingAs($this->admin())->put(route('admin.settings.ai.update'), [
            'endpoint' => 'https://ai.example.test/v1',
        ]);

        $this->assertSame('sk-original-ab12', AiSetting::query()->first()->api_key);
    }

    public function test_a_new_api_key_replaces_the_old_one(): void
    {
        AiSetting::query()->create(['api_key' => 'sk-old']);

        $this->actingAs($this->admin())->put(route('admin.settings.ai.update'), [
            'api_key' => 'sk-new',
        ]);

        $this->assertSame('sk-new', AiSetting::query()->first()->api_key);
    }

    public function test_clear_api_key_checkbox_removes_the_key(): void
    {
        AiSetting::query()->create(['api_key' => 'sk-old']);

        $this->actingAs($this->admin())->put(route('admin.settings.ai.update'), [
            'clear_api_key' => '1',
        ]);

        $this->assertNull(AiSetting::query()->first()->api_key);
    }

    public function test_saving_records_an_audit_log_without_the_key_value(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.ai.update'), [
            'endpoint' => 'https://ai.example.test/v1',
            'api_key' => 'sk-brand-new',
        ]);

        $log = AuditLog::query()->where('action', 'ai_settings.updated')->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->new_values['api_key_changed']);
        $this->assertStringNotContainsString('sk-brand-new', json_encode($log->new_values));
    }

    public function test_leaving_endpoint_blank_falls_back_to_the_env_default(): void
    {
        config(['services.ai.openai_compatible.endpoint' => 'https://fallback.example.test/v1']);
        AiSetting::query()->create(['endpoint' => 'https://custom.example.test/v1']);

        $this->actingAs($this->admin())->put(route('admin.settings.ai.update'), [
            'endpoint' => '',
        ]);

        $this->assertNull(AiSetting::query()->first()->endpoint);
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

    public function test_connection_test_uses_the_already_saved_key_when_the_field_is_left_blank(): void
    {
        AiSetting::query()->create(['endpoint' => 'https://ai.example.test/v1', 'api_key' => 'sk-saved']);
        Http::fake(['ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => '{"ok": true}']]]])]);

        $this->actingAs($this->admin())->postJson(route('admin.settings.ai.test'), [
            'endpoint' => 'https://ai.example.test/v1',
        ])->assertOk();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-saved'));
    }
}
