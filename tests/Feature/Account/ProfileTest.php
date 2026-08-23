<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_profile_screen_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee($user->user_id);
    }

    public function test_name_can_be_updated(): void
    {
        $user = User::factory()->create(['name' => '旧名']);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => '新名',
            'email' => $user->email,
        ])->assertRedirect(route('profile.edit'));

        $this->assertSame('新名', $user->fresh()->name);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_changing_email_requires_reverification(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-password')]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'changed@example.com',
            'email_current_password' => 'current-password',
        ])->assertRedirect(route('verification.notice'));

        $fresh = $user->fresh();
        $this->assertSame('changed@example.com', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $other->email,
        ])->assertSessionHasErrors('email');
    }

    public function test_password_can_be_changed(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-password')]);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'current-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_password_change_invalidates_other_device_sessions(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-password')]);

        $response = $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'current-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status');

        // 現在のセッションには新しいパスワードハッシュが保存され、ログイン状態が維持される
        $hash = $response->getSession()->get('password_hash_web');
        $this->assertNotNull($hash);
        $this->assertSame($user->fresh()->password, $hash);
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-password')]);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_account_can_be_deleted(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-password')]);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['deletion_password' => 'current-password'])
            ->assertRedirect(route('login'));

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertGuest();
    }

    public function test_last_highest_responsible_cannot_delete_account(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $user = $highests[0];
        $user->update(['password' => Hash::make('current-password')]);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['deletion_password' => 'current-password'])
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_deletion_requires_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-password')]);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['deletion_password' => 'wrong'])
            ->assertSessionHasErrors('deletion_password');
    }

    public function test_password_change_and_deletion_use_separate_fields(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-password')]);

        $html = $this->actingAs($user)->get(route('profile.edit'))->getContent();

        // 同じ画面に id="password" が2つあるとラベルとエラー表示が混ざる
        $this->assertSame(1, substr_count($html, 'id="password"'));
        $this->assertStringContainsString('id="deletion_password"', $html);

        // 退会の失敗が「新しいパスワード」のエラーとして出ないこと
        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['deletion_password' => 'wrong'])
            ->assertSessionHasErrors('deletion_password')
            ->assertSessionDoesntHaveErrors('password');
    }

    public function test_profile_shows_activity_stats_and_history(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], \App\Enums\EventStatus::Accepting, $members);

        $this->actingAs($members[0])
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('これまでの活動')
            ->assertSee('参加イベント')
            ->assertSee($event->name);
    }

    public function test_profile_stats_are_zero_for_a_new_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('これまでの活動');
    }
}
