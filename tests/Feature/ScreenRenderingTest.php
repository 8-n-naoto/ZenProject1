<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 主要画面が例外なく描画できることを確認するスモークテスト。
 */
class ScreenRenderingTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_guest_screens_render(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('ログイン');
        $this->get(route('register'))->assertOk()->assertSee('新規登録');
    }

    public function test_verification_notice_renders(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    }

    public function test_dashboard_renders_for_member(): void
    {
        ['group' => $group, 'member' => $members] = $this->makeGroup();

        $this->actingAs($members[0])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($group->name)
            ->assertSee($members[0]->user_id);
    }

    public function test_dashboard_renders_for_user_without_groups(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('まだグループに参加していません');
    }

    public function test_group_screens_render(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();

        $this->actingAs($highests[0])->get(route('groups.index'))->assertOk();
        $this->actingAs($highests[0])->get(route('groups.create'))->assertOk();
        $this->actingAs($highests[0])->get(route('groups.show', $group))->assertOk();
        $this->actingAs($highests[0])->get(route('groups.edit', $group))->assertOk();
        $this->actingAs($highests[0])->get(route('groups.search-users', $group))->assertOk();
    }

    public function test_invitations_screen_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('invitations.index'))
            ->assertOk()
            ->assertSee('返答待ちの招待はありません');
    }

    public function test_account_screens_render(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('アカウント');
    }

    public function test_password_reset_screens_render(): void
    {
        $this->get(route('password.request'))->assertOk();
        $this->get(route('password.reset', ['token' => 'dummy-token']))->assertOk();
    }

    public function test_event_related_screens_render_with_empty_data(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], \App\Enums\EventStatus::Accepting, $members);

        $this->actingAs($highests[0])->get(route('circles.index', $event))->assertOk();
        $this->actingAs($highests[0])->get(route('purchases.personal.index', $event))->assertOk();
        $this->actingAs($highests[0])->get(route('purchases.shared.index', $event))->assertOk();
        $this->actingAs($highests[0])->get(route('purchases.summary', $event))->assertOk();
        $this->actingAs($highests[0])->get(route('approvals.index', $event))->assertOk();
        $this->actingAs($highests[0])->get(route('histories.index', $event))->assertOk();
    }

    public function test_result_and_settlement_screens_render(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], \App\Enums\EventStatus::Settling, $members);

        $this->actingAs($highests[0])->get(route('results.index', $event))->assertOk();
        $this->actingAs($highests[0])->get(route('settlements.index', $event))->assertOk();
    }

    public function test_notifications_screen_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('お知らせ');
    }

    public function test_every_page_declares_the_mobile_viewport(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();

        foreach ([route('dashboard'), route('groups.index'), route('groups.show', $group), route('notifications.index')] as $url) {
            $this->actingAs($highests[0])
                ->get($url)
                ->assertOk()
                ->assertSee('width=device-width, initial-scale=1', false)
                ->assertSee('css/app.css', false);
        }
    }

    public function test_stylesheet_is_bundled(): void
    {
        $this->assertFileExists(public_path('css/app.css'));
    }
}
