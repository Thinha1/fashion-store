<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Tên mới',
            'email' => $user->email,
            'phone' => '0911111111',
        ]);

        $response->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Tên mới', $user->name);
        $this->assertSame('0911111111', $user->phone);
    }

    public function test_changing_the_email_keeps_the_customer_signed_in_without_verification(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => 'new-address@example.com',
        ]);

        $user->refresh();

        $this->assertSame('new-address@example.com', $user->email);
        $this->assertAuthenticatedAs($user);
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }
}
