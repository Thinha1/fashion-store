<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupplierTaxLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function url(string $taxCode = '0316794479'): string
    {
        return route('admin.suppliers.tax-lookup', ['tax_code' => $taxCode]);
    }

    private function company(string $id = '0316794479'): array
    {
        return ['code' => '00', 'data' => ['id' => $id, 'name' => 'Công ty mẫu', 'address' => 'TP Hồ Chí Minh']];
    }

    public function test_lookup_requires_supplier_permission(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($this->url())->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_valid_lookup_is_cached_without_creating_a_supplier(): void
    {
        Http::fake(['api.vietqr.io/*' => Http::response($this->company())]);
        $this->actingAs(User::factory()->admin()->create());
        for ($i = 0; $i < 2; $i++) {
            $this->getJson($this->url())->assertOk()->assertExactJson(['data' => [
                'tax_code' => '0316794479', 'name' => 'Công ty mẫu', 'address' => 'TP Hồ Chí Minh',
            ]]);
        }
        Http::assertSentCount(1);
        $this->assertDatabaseCount('suppliers', 0);
    }

    public function test_branch_codes_with_or_without_hyphen_share_a_cache_entry(): void
    {
        Http::fake(['api.vietqr.io/*' => Http::response($this->company('0316794479-001'))]);
        $this->actingAs(User::factory()->admin()->create());
        foreach (['0316794479001', '0316794479-001'] as $code) {
            $this->getJson($this->url($code))->assertOk()->assertJsonPath('data.tax_code', '0316794479-001');
        }
        Http::assertSent(fn ($request) => $request->url() === 'https://api.vietqr.io/v2/business/0316794479-001');
        Http::assertSentCount(1);
    }

    public function test_cached_details_refresh_after_configured_expiry(): void
    {
        config(['services.vietqr.business_cache_seconds' => 60]);
        $updatedCompany = $this->company();
        $updatedCompany['data']['address'] = 'Địa chỉ mới';
        Http::fake(['api.vietqr.io/*' => Http::sequence()->push($this->company())->push($updatedCompany)]);
        $this->actingAs(User::factory()->admin()->create());

        $this->getJson($this->url())->assertOk()->assertJsonPath('data.address', 'TP Hồ Chí Minh');
        $this->travel(59)->seconds();
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.address', 'TP Hồ Chí Minh');
        Http::assertSentCount(1);
        $this->travel(2)->seconds();
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.address', 'Địa chỉ mới');
        Http::assertSentCount(2);
    }

    public function test_cache_can_be_disabled(): void
    {
        config(['services.vietqr.business_cache_seconds' => 0]);
        Http::fake(['api.vietqr.io/*' => Http::response($this->company())]);
        $this->actingAs(User::factory()->admin()->create());
        $this->getJson($this->url())->assertOk();
        $this->getJson($this->url())->assertOk();
        Http::assertSentCount(2);
    }

    public static function invalidCodes(): array
    {
        return [[''], ['123'], ['0316794479/..'], ['abcdefghij'], ['0316794479-01']];
    }

    #[DataProvider('invalidCodes')]
    public function test_invalid_input_never_calls_provider(string $taxCode): void
    {
        $this->actingAs(User::factory()->admin()->create())->getJson($this->url($taxCode))
            ->assertUnprocessable()->assertJsonValidationErrors('tax_code');
        Http::assertNothingSent();
    }

    public static function failedResponses(): array
    {
        return [
            'not found' => [['code' => '51', 'data' => null], 200, 404],
            'rate limited' => [[], 429, 503],
            'server error' => [[], 500, 503],
            'unexpected response' => ['not json', 200, 502],
            'missing data' => [['code' => '00', 'data' => null], 200, 502],
            'incomplete company' => [['code' => '00', 'data' => ['id' => '0316794479', 'name' => 'Example']], 200, 502],
            'wrong company' => [['code' => '00', 'data' => ['id' => '0100000000', 'name' => 'Other', 'address' => 'Other']], 200, 502],
        ];
    }

    #[DataProvider('failedResponses')]
    public function test_provider_failures_are_not_cached(array|string $body, int $status, int $expected): void
    {
        Http::fake(['api.vietqr.io/*' => Http::sequence()->push($body, $status)->push($this->company())]);
        $this->actingAs(User::factory()->admin()->create());
        $this->getJson($this->url())->assertStatus($expected)->assertJsonStructure(['message']);
        $this->getJson($this->url())->assertOk();
        Http::assertSentCount(2);
    }

    public function test_connection_failure_returns_a_retryable_message(): void
    {
        Http::fake(['api.vietqr.io/*' => Http::failedConnection()]);
        $this->actingAs(User::factory()->admin()->create())->getJson($this->url())->assertStatus(503)
            ->assertJsonPath('message', 'Chưa thể tra cứu mã số thuế. Vui lòng thử lại sau hoặc nhập thông tin thủ công.');
    }

    public function test_provider_throttling_returns_a_retry_after_header(): void
    {
        Http::fake(['api.vietqr.io/*' => Http::response([], 429, ['Retry-After' => '120'])]);
        $this->actingAs(User::factory()->admin()->create())->getJson($this->url())
            ->assertStatus(503)->assertHeader('Retry-After', '120');
    }

    public function test_lookup_is_rate_limited(): void
    {
        $limit = 2;
        config(['services.vietqr.business_requests_per_minute' => $limit]);
        Http::fake(['api.vietqr.io/*' => Http::response($this->company())]);
        $this->actingAs(User::factory()->admin()->create());
        for ($i = 0; $i < $limit; $i++) {
            $this->getJson($this->url())->assertOk();
        }
        $this->getJson($this->url())->assertStatus(429);
        $this->actingAs(User::factory()->admin()->create())->getJson($this->url())->assertOk();
        Http::assertSentCount(1);
    }
}
