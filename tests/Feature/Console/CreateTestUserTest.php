<?php

namespace Tests\Feature\Console;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CreateTestUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_user_can_log_in_immediately(): void
    {
        $this->artisan('users:create-test taro123 --password=secret123')->assertExitCode(0);

        $user = User::where('user_id', 'taro123')->firstOrFail();

        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Auth::attempt(['user_id' => 'taro123', 'password' => 'secret123']));
    }

    public function test_defaults_fill_in_name_and_email(): void
    {
        $this->artisan('users:create-test taro123')->assertExitCode(0);

        $user = User::where('user_id', 'taro123')->firstOrFail();

        $this->assertSame('taro123', $user->name);
        $this->assertSame('taro123@example.com', $user->email);
    }

    public function test_user_ids_are_generated_in_order(): void
    {
        $this->artisan('users:create-test --count=3')->assertExitCode(0);

        $this->assertSame(
            ['test001', 'test002', 'test003'],
            User::orderBy('id')->pluck('user_id')->all()
        );
    }

    /** 退会済みユーザーのIDはDBに残るため、自動生成で衝突してはいけない */
    public function test_generated_user_ids_skip_withdrawn_users(): void
    {
        $this->artisan('users:create-test --count=2')->assertExitCode(0);
        User::where('user_id', 'test002')->firstOrFail()->delete();

        $this->artisan('users:create-test')->assertExitCode(0);

        $this->assertSame('test003', User::orderByDesc('id')->firstOrFail()->user_id);
    }

    public function test_duplicate_user_id_fails_without_force(): void
    {
        $this->artisan('users:create-test taro123')->assertExitCode(0);

        $this->artisan('users:create-test taro123')->assertExitCode(1);

        $this->assertSame(1, User::where('user_id', 'taro123')->count());
    }

    public function test_force_resets_the_password_of_an_existing_user(): void
    {
        $this->artisan('users:create-test taro123 --password=oldpassword')->assertExitCode(0);

        $this->artisan('users:create-test taro123 --password=newpassword --force')->assertExitCode(0);

        $this->assertSame(1, User::where('user_id', 'taro123')->count());
        $this->assertTrue(Auth::attempt(['user_id' => 'taro123', 'password' => 'newpassword']));
    }

    public function test_force_keeps_the_existing_name_and_email(): void
    {
        $this->artisan('users:create-test taro123 --name=太郎 --email=taro@example.com')->assertExitCode(0);

        $this->artisan('users:create-test taro123 --password=newpassword --force')->assertExitCode(0);

        $user = User::where('user_id', 'taro123')->firstOrFail();
        $this->assertSame('太郎', $user->name);
        $this->assertSame('taro@example.com', $user->email);
    }

    public function test_force_restores_a_withdrawn_user(): void
    {
        $this->artisan('users:create-test taro123')->assertExitCode(0);
        User::where('user_id', 'taro123')->firstOrFail()->delete();

        $this->artisan('users:create-test taro123 --password=newpassword --force')->assertExitCode(0);

        $user = User::where('user_id', 'taro123')->firstOrFail();
        $this->assertFalse($user->trashed());
        $this->assertTrue(Auth::attempt(['user_id' => 'taro123', 'password' => 'newpassword']));
    }

    public function test_unverified_option_leaves_the_email_unverified(): void
    {
        $this->artisan('users:create-test taro123 --unverified')->assertExitCode(0);

        $this->assertFalse(User::where('user_id', 'taro123')->firstOrFail()->hasVerifiedEmail());
    }

    public function test_invalid_user_id_is_rejected(): void
    {
        $this->artisan('users:create-test TARO')->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_short_password_is_rejected(): void
    {
        $this->artisan('users:create-test taro123 --password=short')->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_user_can_join_a_group_with_a_role(): void
    {
        $group = Group::factory()->create(['name' => '冬コミ有志の会']);

        $this->artisan('users:create-test taro123 --group=冬コミ有志の会 --role=responsible')->assertExitCode(0);

        $user = User::where('user_id', 'taro123')->firstOrFail();
        $membership = $group->activeMembers()->where('users.id', $user->id)->firstOrFail();

        $this->assertSame(GroupRole::Responsible->value, $membership->pivot->role);
    }

    public function test_group_can_be_specified_by_id(): void
    {
        $group = Group::factory()->create();

        $this->artisan('users:create-test taro123 --group='.$group->id)->assertExitCode(0);

        $this->assertSame(1, $group->activeMembers()->count());
    }

    public function test_unknown_group_fails(): void
    {
        $this->artisan('users:create-test taro123 --group=存在しない会')->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_unknown_role_fails(): void
    {
        $this->artisan('users:create-test taro123 --role=boss')->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_count_cannot_be_combined_with_an_explicit_user_id(): void
    {
        $this->artisan('users:create-test taro123 --count=3')->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_count_out_of_range_fails(): void
    {
        $this->artisan('users:create-test --count=0')->assertExitCode(1);
        $this->artisan('users:create-test --count=51')->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_command_is_refused_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('users:create-test taro123')->assertExitCode(1);

        $this->assertSame(0, User::count());
    }
}
