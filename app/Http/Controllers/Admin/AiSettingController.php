<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAiSettingRequest;
use App\Models\AiSetting;
use App\Models\AuditLog;
use App\Services\Ai\AiSettings;
use App\Services\Ai\OpenAiCompatibleProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

/**
 * Lets an admin point both AI agents (the product-draft assistant and the
 * customer-facing shopping assistant — they share one backend, see
 * OpenAiCompatibleProvider) at a different endpoint/model/key without
 * touching .env or restarting the app. Guarded by its own "settings.manage"
 * permission, distinct from products.manage/staff.manage: an API key is a
 * different trust tier than catalog editing.
 */
class AiSettingController extends Controller
{
    public function edit(): View
    {
        $setting = AiSetting::query()->first();

        return view('admin.settings.ai', [
            'setting' => $setting,
            'maskedApiKey' => $this->mask($setting?->api_key),
        ]);
    }

    public function update(UpdateAiSettingRequest $request): RedirectResponse
    {
        $setting = AiSetting::query()->firstOrNew();

        $apiKeyChanged = $request->boolean('clear_api_key') || $request->filled('api_key');

        $setting->fill([
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

        $setting->save();

        AuditLog::query()->create([
            'actor_id' => Auth::id(),
            'action' => 'ai_settings.updated',
            'subject_type' => $setting->getMorphClass(),
            'subject_id' => $setting->id,
            // Never the key itself — only whether this save touched it.
            'new_values' => [
                'endpoint' => $setting->endpoint,
                'model' => $setting->model,
                'max_tokens' => $setting->max_tokens,
                'api_key_changed' => $apiKeyChanged,
            ],
        ]);

        return redirect()->route('admin.settings.ai.edit')
            ->with('status', 'Đã lưu cấu hình agent AI.');
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
            'endpoint' => ['nullable', 'string', 'max:255', 'url'],
            'model' => ['nullable', 'string', 'max:255'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'api_key' => ['nullable', 'string', 'max:1000'],
        ]);

        // Built from the form's CURRENT (not-yet-submitted) input, never the
        // persisted row — this is what lets "Kiểm tra kết nối" catch a typo
        // before Save, not just verify what was already saved.
        $current = AiSettings::current();
        $candidate = new AiSetting([
            'endpoint' => ($validated['endpoint'] ?? '') !== '' ? $validated['endpoint'] : $current->endpoint(),
            'model' => ($validated['model'] ?? '') !== '' ? $validated['model'] : $current->model(),
            'max_tokens' => $validated['max_tokens'] ?? $current->maxTokens(),
            // Blank in the test call means "use whatever is already
            // effective" (persisted, or .env) — same rule as saving.
            'api_key' => ($validated['api_key'] ?? '') !== '' ? $validated['api_key'] : $current->apiKey(),
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

    private function mask(?string $apiKey): ?string
    {
        if (! $apiKey) {
            return null;
        }

        return Str::length($apiKey) <= 4 ? str_repeat('•', 8) : str_repeat('•', 8).Str::substr($apiKey, -4);
    }
}
