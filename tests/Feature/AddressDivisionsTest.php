<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressDivisionsTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://provinces.test/api/v2';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.vn_divisions.base_url' => self::API]);
        Http::preventStrayRequests();
    }

    private function fakeApi(): void
    {
        Http::fake([
            self::API.'/p/' => Http::response([
                ['name' => 'Thành phố Hồ Chí Minh', 'code' => 79, 'wards' => []],
                ['name' => 'Thành phố Hà Nội', 'code' => 1, 'wards' => []],
                ['name' => 'Tỉnh An Giang', 'code' => 91, 'wards' => []],
            ]),
            self::API.'/p/79?depth=2' => Http::response([
                'name' => 'Thành phố Hồ Chí Minh', 'code' => 79,
                'wards' => [
                    ['name' => 'Phường Sài Gòn', 'code' => 26740, 'province_code' => 79],
                    ['name' => 'Phường Bến Thành', 'code' => 26743, 'province_code' => 79],
                ],
            ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'recipient_name' => 'Nguyễn Văn A',
            'phone' => '0901234567',
            'province_name' => 'Thành phố Hồ Chí Minh',
            'ward_name' => 'Phường Bến Thành',
            'address_line' => '12 Lê Lợi',
        ], $overrides);
    }

    public function test_form_lists_provinces_sorted_by_name(): void
    {
        $this->fakeApi();

        $this->actingAs(User::factory()->create())->get(route('addresses.create'))
            ->assertOk()
            ->assertSeeInOrder(['<option value="Tỉnh An Giang"', '>An Giang<', '>Hà Nội<', '>Hồ Chí Minh<'], false)
            ->assertDontSee('Quận/Huyện');
    }

    public function test_saved_address_uses_the_official_names_and_no_district(): void
    {
        $this->fakeApi();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('addresses.store'), $this->payload([
            'ward_name' => '  phường bến   thành ',
            'district_name' => 'Quận 1',
            'province_code' => '1',
        ]))->assertSessionHasNoErrors();

        $address = $user->addresses()->sole();
        $this->assertSame('Phường Bến Thành', $address->ward_name);
        $this->assertSame('79', $address->province_code);
        $this->assertNull($address->district_name);
        $this->assertSame('12 Lê Lợi, Phường Bến Thành, Thành phố Hồ Chí Minh', $address->fullAddress());
    }

    public function test_editing_an_old_address_clears_its_district(): void
    {
        $this->fakeApi();
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create(['province_name' => 'TP. Hồ Chí Minh', 'district_name' => 'Quận 1']);

        $this->actingAs($user)->put(route('addresses.update', $address), $this->payload())->assertSessionHasNoErrors();

        $this->assertNull($address->fresh()->district_name);
        $this->assertSame('Thành phố Hồ Chí Minh', $address->fresh()->province_name);
    }

    public function test_unknown_province_or_ward_is_rejected(): void
    {
        $this->fakeApi();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('addresses.store'), $this->payload(['province_name' => 'TP. Hồ Chí Minh']))
            ->assertSessionHasErrors(['province_name' => 'Vui lòng chọn Tỉnh/Thành phố trong danh sách.']);
        $this->actingAs($user)->post(route('addresses.store'), $this->payload(['ward_name' => 'Phường Bến Nghé']))
            ->assertSessionHasErrors('ward_name');

        $this->assertSame(0, $user->addresses()->count());
    }

    public function test_new_address_at_checkout_is_checked_too(): void
    {
        $this->fakeApi();

        $this->actingAs(User::factory()->create())->post(route('checkout.store'), $this->payload([
            'address_id' => 'new',
            'ward_name' => 'Phường Kim Mã',
            'payment_method' => 'cod',
        ]))->assertSessionHasErrors('ward_name');
    }

    public function test_wards_endpoint_returns_a_province_wards(): void
    {
        $this->fakeApi();
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('addresses.wards', ['provinceCode' => 79]))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Phường Bến Thành')
            ->assertJsonCount(2, 'data');
        $this->actingAs($user)->getJson(route('addresses.wards', ['provinceCode' => 5]))->assertNotFound();
    }

    public function test_wards_endpoint_needs_a_signed_in_customer(): void
    {
        $this->getJson(route('addresses.wards', ['provinceCode' => 79]))->assertUnauthorized();
    }

    public function test_lists_are_cached(): void
    {
        $this->fakeApi();
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('addresses.wards', ['provinceCode' => 79]))->assertOk();
        $this->actingAs($user)->getJson(route('addresses.wards', ['provinceCode' => 79]))->assertOk();
        $this->actingAs($user)->get(route('addresses.create'))->assertOk();

        Http::assertSentCount(2);
    }

    public function test_when_the_api_is_down_addresses_are_typed_freely(): void
    {
        Http::fake([self::API.'/*' => Http::failedConnection()]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('addresses.create'))
            ->assertOk()
            ->assertDontSee('<select id="province_name"', false);
        $this->actingAs($user)->post(route('addresses.store'), $this->payload(['province_name' => 'TP. Hồ Chí Minh', 'ward_name' => 'Phường Bến Nghé']))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->getJson(route('addresses.wards', ['provinceCode' => 79]))->assertServiceUnavailable();

        $this->assertSame('TP. Hồ Chí Minh', $user->addresses()->sole()->province_name);
        Http::assertSentCount(1);
    }
}
