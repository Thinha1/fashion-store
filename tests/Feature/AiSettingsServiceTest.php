<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Services\Ai\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ai.openai_compatible.endpoint' => 'https://fallback.example.test/v1',
            'services.ai.openai_compatible.key' => 'fallback-key',
            'services.ai.openai_compatible.model' => 'fallback-model',
            'services.ai.max_tokens' => 1234,
        ]);
    }

    public function test_falls_back_to_config_when_no_row_exists(): void
    {
        $settings = AiSettings::current();

        $this->assertSame('https://fallback.example.test/v1', $settings->endpoint());
        $this->assertSame('fallback-key', $settings->apiKey());
        $this->assertSame('fallback-model', $settings->model());
        $this->assertSame(1234, $settings->maxTokens());
    }

    public function test_falls_back_to_config_per_field_when_the_row_only_sets_some(): void
    {
        AiSetting::query()->create(['endpoint' => 'https://custom.example.test/v1']);

        $settings = AiSettings::current();

        $this->assertSame('https://custom.example.test/v1', $settings->endpoint());
        $this->assertSame('fallback-key', $settings->apiKey());
        $this->assertSame('fallback-model', $settings->model());
        $this->assertSame(1234, $settings->maxTokens());
    }

    public function test_uses_the_saved_row_when_every_field_is_set(): void
    {
        AiSetting::query()->create([
            'endpoint' => 'https://custom.example.test/v1',
            'api_key' => 'custom-key',
            'model' => 'custom-model',
            'max_tokens' => 4096,
        ]);

        $settings = AiSettings::current();

        $this->assertSame('https://custom.example.test/v1', $settings->endpoint());
        $this->assertSame('custom-key', $settings->apiKey());
        $this->assertSame('custom-model', $settings->model());
        $this->assertSame(4096, $settings->maxTokens());
    }
}
