<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
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

        $response->assertRedirect(route('dashboard'));
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

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        User::factory()->create([
            'user_id' => 'test001',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'user_id' => 'test001',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('user_id');
            $this->flushSession();
        }

        // 6回目は正しいパスワードでもロックアウトされる
        $response = $this->post('/login', [
            'user_id' => 'test001',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('user_id');
        $this->assertGuest();

        $errors = session('errors')->get('user_id');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('試行回数が多すぎます', $errors[0]);
    }

    public function test_throttle_counter_is_cleared_after_successful_login(): void
    {
        $user = User::factory()->create([
            'user_id' => 'test001',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', [
                'user_id' => 'test001',
                'password' => 'wrong-password',
            ]);
            $this->flushSession();
        }

        $this->post('/login', [
            'user_id' => 'test001',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $this->flushSession();

        // カウンタがリセットされているため、再度5回まで試行できる
        $response = null;
        for ($i = 0; $i < 5; $i++) {
            if ($i > 0) {
                $this->flushSession();
            }
            $response = $this->post('/login', [
                'user_id' => 'test001',
                'password' => 'wrong-password',
            ]);
            $response->assertSessionHasErrors('user_id');
        }

        $errors = $response->getSession()->get('errors')->get('user_id');
        $this->assertStringNotContainsString('試行回数が多すぎます', $errors[0]);
    }

    public function test_throttle_key_is_scoped_per_user_id(): void
    {
        User::factory()->create([
            'user_id' => 'test001',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);
        $other = User::factory()->create([
            'user_id' => 'test002',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'user_id' => 'test001',
                'password' => 'wrong-password',
            ]);
            $this->flushSession();
        }

        // 別ユーザーは影響を受けない
        $this->post('/login', [
            'user_id' => 'test002',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($other);

        RateLimiter::clear('test001|127.0.0.1');
    }
}
