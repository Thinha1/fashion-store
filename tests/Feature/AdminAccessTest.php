<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_admin_access_gets_403(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertForbidden();
    }

    public function test_unverified_admin_is_redirected_to_verification_notice(): void
    {
        $admin = User::factory()->admin()->unverified()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_admin_user_can_access_the_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
    }
}
