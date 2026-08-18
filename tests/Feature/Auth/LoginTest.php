<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_can_be_displayed(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertViewIs('auth.login');
    }

    public function test_verified_user_can_login_with_user_id_and_password(): void
    {
        $user = User::factory()->create([
            'user_id' => 'test001',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $response = $this->post('/login', [
            'user_id' => 'test001',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('top'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'user_id' => 'test001',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $response = $this->post('/login', [
            'user_id' => 'test001',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('user_id');
        $this->assertGuest();
    }

    public function test_unverified_user_is_redirected_to_email_verification(): void
    {
        $user = User::factory()->create([
            'user_id' => 'test001',
            'password' => Hash::make('password123'),
            'email_verified_at' => null,
        ]);

        $response = $this->post('/login', [
            'user_id' => 'test001',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create([
            'user_id' => 'test001',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
