<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_user_can_register_and_is_assigned_the_customer_role(): void
    {
        Event::fake();

        Role::factory()->create(['code' => 'customer', 'name' => 'Khách hàng']);

        $response = $this->post(route('register'), [
            'name' => 'Nguyễn Văn A',
            'email' => 'a@example.com',
            'phone' => '0900000000',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'a@example.com')->firstOrFail();

        $this->assertSame('customer', $user->role->code);
    }

    public function test_registration_requires_unique_email(): void
    {
        Role::factory()->create(['code' => 'customer']);
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post(route('register'), [
            'name' => 'Nguyễn Văn B',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
