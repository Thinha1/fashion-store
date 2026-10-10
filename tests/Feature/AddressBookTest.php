<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressBookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'recipient_name' => 'Nguyễn Văn A',
            'phone' => '0901234567',
            'province_name' => 'TP. Hồ Chí Minh',
            'district_name' => 'Quận 1',
            'ward_name' => 'Phường Bến Nghé',
            'address_line' => '12 Lê Lợi',
        ], $overrides);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('addresses.index'))->assertRedirect(route('login'));
    }

    public function test_customer_sees_only_their_own_addresses(): void
    {
        $user = User::factory()->create();
        Address::factory()->for($user)->create(['address_line' => '12 Lê Lợi']);
        Address::factory()->create(['address_line' => '99 Hai Bà Trưng']);

        $this->actingAs($user)->get(route('addresses.index'))
            ->assertOk()
            ->assertSee('12 Lê Lợi')
            ->assertDontSee('99 Hai Bà Trưng');
    }

    public function test_first_address_becomes_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('addresses.store'), $this->payload())
            ->assertRedirect(route('addresses.index'));

        $this->assertTrue($user->addresses()->sole()->is_default);
    }

    public function test_new_default_address_replaces_the_old_default(): void
    {
        $user = User::factory()->create();
        $old = Address::factory()->for($user)->default()->create();

        $this->actingAs($user)->post(route('addresses.store'), $this->payload(['is_default' => '1']));

        $this->assertFalse($old->fresh()->is_default);
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
    }

    public function test_second_address_is_not_default_unless_asked(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->for($user)->default()->create();

        $this->actingAs($user)->post(route('addresses.store'), $this->payload());

        $this->assertTrue($first->fresh()->is_default);
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('addresses.store'), $this->payload(['phone' => '12345']))
            ->assertSessionHasErrors('phone');
    }

    public function test_customer_can_update_and_mark_an_address_default(): void
    {
        $user = User::factory()->create();
        $default = Address::factory()->for($user)->default()->create();
        $other = Address::factory()->for($user)->create();

        $this->actingAs($user)->put(route('addresses.update', $other), $this->payload(['address_line' => '5 Nguyễn Huệ']))
            ->assertRedirect(route('addresses.index'));
        $this->assertSame('5 Nguyễn Huệ', $other->fresh()->address_line);

        $this->actingAs($user)->patch(route('addresses.default', $other));
        $this->assertTrue($other->fresh()->is_default);
        $this->assertFalse($default->fresh()->is_default);
    }

    public function test_deleting_the_default_address_promotes_another(): void
    {
        $user = User::factory()->create();
        $default = Address::factory()->for($user)->default()->create();
        $other = Address::factory()->for($user)->create();

        $this->actingAs($user)->delete(route('addresses.destroy', $default))
            ->assertRedirect(route('addresses.index'));

        $this->assertModelMissing($default);
        $this->assertTrue($other->fresh()->is_default);
    }

    public function test_customer_cannot_touch_someone_elses_address(): void
    {
        $address = Address::factory()->default()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('addresses.edit', $address))->assertForbidden();
        $this->actingAs($intruder)->put(route('addresses.update', $address), $this->payload())->assertForbidden();
        $this->actingAs($intruder)->patch(route('addresses.default', $address))->assertForbidden();
        $this->actingAs($intruder)->delete(route('addresses.destroy', $address))->assertForbidden();

        $this->assertModelExists($address);
    }
}
