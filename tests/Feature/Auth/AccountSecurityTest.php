<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_email_requires_the_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'me@example.com',
            'password' => Hash::make('current-password'),
        ]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'attacker@example.com',
        ])->assertSessionHasErrors('email_current_password');

        $this->assertSame('me@example.com', $user->fresh()->email);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'attacker@example.com',
            'email_current_password' => 'wrong',
        ])->assertSessionHasErrors('email_current_password');

        $this->assertSame('me@example.com', $user->fresh()->email);
    }

    public function test_renaming_without_touching_the_email_needs_no_password(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => '新しい名前',
            'email' => 'me@example.com',
        ])->assertRedirect(route('profile.edit'));

        $this->assertSame('新しい名前', $user->fresh()->name);
    }

    public function test_reset_token_is_invalidated_when_the_email_changes(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'shared@example.com',
            'password' => Hash::make('current-password'),
        ]);

        $this->post(route('password.email'), ['email' => 'shared@example.com'])->assertRedirect();
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'shared@example.com']);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'moved@example.com',
            'email_current_password' => 'current-password',
        ])->assertRedirect();

        // 旧アドレス宛のトークンは残っていない
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'shared@example.com']);

        // そのアドレスで別人が登録しても、古いリンクは効かない
        $other = User::factory()->create([
            'email' => 'shared@example.com',
            'password' => Hash::make('other-password'),
        ]);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'shared@example.com',
            'password' => 'hijacked-password',
            'password_confirmation' => 'hijacked-password',
        ]);

        $this->assertTrue(Hash::check('other-password', $other->fresh()->password));
        $this->assertFalse(Hash::check('hijacked-password', $other->fresh()->password));
    }

    public function test_password_reset_requests_are_throttled(): void
    {
        Notification::fake();

        $statuses = [];

        for ($i = 0; $i < 10; $i++) {
            $statuses[] = $this->post(route('password.email'), ['email' => 'user'.$i.'@example.com'])->getStatusCode();
        }

        $this->assertContains(429, $statuses, '再設定メールの送信に回数制限がありません');
    }

    public function test_reset_submissions_are_throttled(): void
    {
        $statuses = [];

        for ($i = 0; $i < 10; $i++) {
            $statuses[] = $this->post(route('password.store'), [
                'token' => 'invalid-token-'.$i,
                'email' => 'user@example.com',
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])->getStatusCode();
        }

        $this->assertContains(429, $statuses, '再設定の試行に回数制限がありません');
    }
}
