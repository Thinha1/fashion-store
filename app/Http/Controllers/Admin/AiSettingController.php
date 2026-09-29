<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveAiSettingRequest;
use App\Models\AiSetting;
use App\Models\AuditLog;
use App\Services\Ai\AiSettings;
use App\Services\Ai\OpenAiCompatibleProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Lets an admin keep several saved backend configurations for both AI agents
 * (the product-draft assistant and the customer-facing shopping assistant —
 * they share one backend, see OpenAiCompatibleProvider) and pick which one is
 * primary, i.e. actually called, without touching .env or restarting the app.
 * Guarded by its own "settings.manage" permission, distinct from
 * products.manage/staff.manage: an API key is a different trust tier than
 * catalog editing.
 */
class AiSettingController extends Controller
{
    public function edit(Request $request): View
    {
        $editing = $request->filled('sua') ? AiSetting::query()->findOrFail($request->integer('sua')) : null;

        return view('admin.settings.ai', [
            'settings' => AiSetting::query()->with('updatedBy')->orderByDesc('is_primary')->orderBy('id')->get(),
            'editing' => $editing,
            'envDefaults' => [
                'endpoint' => (string) config('services.ai.openai_compatible.endpoint'),
                'model' => (string) config('services.ai.openai_compatible.model'),
            ],
        ]);
    }

    public function store(SaveAiSettingRequest $request): RedirectResponse
    {
        $setting = DB::transaction(function () use ($request): AiSetting {
            $setting = new AiSetting;
            $this->fillFromRequest($setting, $request);
            // The very first configuration becomes primary straight away —
            // otherwise saving it would silently change nothing.
            $setting->is_primary = ! AiSetting::query()->where('is_primary', true)->exists();
            $setting->save();

            return $setting;
        });

        $this->audit('ai_settings.created', $setting, $request->filled('api_key'));

        return redirect()->route('admin.settings.ai.edit')
            ->with('status', 'Đã thêm cấu hình AI "'.$setting->name.'".');
    }

    public function update(SaveAiSettingRequest $request, AiSetting $aiSetting): RedirectResponse
    {
        $apiKeyChanged = $request->boolean('clear_api_key') || $request->filled('api_key');

        $this->fillFromRequest($aiSetting, $request);
        $aiSetting->save();

        $this->audit('ai_settings.updated', $aiSetting, $apiKeyChanged);

        return redirect()->route('admin.settings.ai.edit')
            ->with('status', 'Đã lưu cấu hình AI "'.$aiSetting->name.'".');
    }

    public function makePrimary(AiSetting $aiSetting): RedirectResponse
    {
        // One transaction so a reader never sees zero or two primaries.
        DB::transaction(function () use ($aiSetting): void {
            AiSetting::query()->where('id', '!=', $aiSetting->id)->where('is_primary', true)->update(['is_primary' => false]);
            $aiSetting->forceFill(['is_primary' => true, 'updated_by' => Auth::id()])->save();
        });

        $this->audit('ai_settings.primary_changed', $aiSetting, false);

        return redirect()->route('admin.settings.ai.edit')
            ->with('status', 'Đã chuyển sang dùng cấu hình AI "'.$aiSetting->name.'".');
    }

    public function destroy(AiSetting $aiSetting): RedirectResponse
    {
        if ($aiSetting->is_primary) {
            return redirect()->route('admin.settings.ai.edit')
                ->withErrors(['ai_setting' => 'Không thể xoá cấu hình đang dùng. Hãy đặt một cấu hình khác làm chính trước.']);
        }

        $aiSetting->delete();

        $this->audit('ai_settings.deleted', $aiSetting, false);

        return redirect()->route('admin.settings.ai.edit')
            ->with('status', 'Đã xoá cấu hình AI "'.$aiSetting->name.'".');
    }

    /**
     * Tries the settings currently typed into the form (not necessarily
     * saved yet) against the real provider, so a wrong endpoint/model/key
     * is caught before it reaches a real customer — the same class of bug
     * a missing "/v1" suffix caused before this screen existed.
     */
    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'setting_id' => ['nullable', 'integer'],
            'endpoint' => ['nullable', 'string', 'max:255', 'url'],
            'model' => ['nullable', 'string', 'max:255'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'api_key' => ['nullable', 'string', 'max:1000'],
        ]);

        // Blank in the test call means "whatever this configuration would
        // effectively use": its own saved value when editing one, else .env —
        // the same rule as saving and the same per-field fallback the agents
        // apply at runtime.
        $base = new AiSettings(isset($validated['setting_id']) ? AiSetting::query()->find($validated['setting_id']) : null);

        // Built from the form's CURRENT (not-yet-submitted) input, never the
        // persisted row — this is what lets "Kiểm tra kết nối" catch a typo
        // before Save, not just verify what was already saved.
        $candidate = new AiSetting([
            'endpoint' => ($validated['endpoint'] ?? '') !== '' ? $validated['endpoint'] : $base->endpoint(),
            'model' => ($validated['model'] ?? '') !== '' ? $validated['model'] : $base->model(),
            'max_tokens' => $validated['max_tokens'] ?? $base->maxTokens(),
            'api_key' => ($validated['api_key'] ?? '') !== '' ? $validated['api_key'] : $base->apiKey(),
        ]);

        $testProvider = new OpenAiCompatibleProvider(new AiSettings($candidate));

        try {
            $testProvider->complete(
                [['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Kiểm tra kết nối.']]]],
                'Đây là yêu cầu kiểm tra kết nối. Chỉ trả lời một object JSON duy nhất: {"ok": true}',
            );
        } catch (ConnectionException|RuntimeException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 200);
        }

        return response()->json(['ok' => true, 'message' => 'Kết nối thành công.']);
    }

    private function fillFromRequest(AiSetting $setting, SaveAiSettingRequest $request): void
    {
        $setting->fill([
            'name' => $request->string('name')->trim()->value(),
            'endpoint' => $request->string('endpoint')->trim()->value() ?: null,
            'model' => $request->string('model')->trim()->value() ?: null,
            'max_tokens' => $request->input('max_tokens') ?: null,
            'updated_by' => Auth::id(),
        ]);

        // Blank api_key input means "leave it as-is" — only an explicit
        // "clear" checkbox or a non-empty value ever touches the stored key,
        // so re-saving the rest of the form never accidentally wipes it.
        if ($request->boolean('clear_api_key')) {
            $setting->api_key = null;
        } elseif ($request->filled('api_key')) {
            $setting->api_key = $request->string('api_key')->value();
        }
    }

    private function audit(string $action, AiSetting $setting, bool $apiKeyChanged): void
    {
        AuditLog::query()->create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $setting->getMorphClass(),
            'subject_id' => $setting->id,
            // Never the key itself — only whether this save touched it.
            'new_values' => [
                'name' => $setting->name,
                'endpoint' => $setting->endpoint,
                'model' => $setting->model,
                'max_tokens' => $setting->max_tokens,
                'is_primary' => $setting->is_primary,
                'api_key_changed' => $apiKeyChanged,
            ],
        ]);
    }
}
