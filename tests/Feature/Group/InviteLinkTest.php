<?php

namespace Tests\Feature\Group;

use App\Enums\GroupRole;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Group\JoinController;
use App\Models\GroupInviteLink;
use App\Models\User;
use App\Services\GroupInviteLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class InviteLinkTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_responsible_can_issue_a_link_and_a_stranger_can_join(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $newcomer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($highests[0])
            ->post(route('groups.invite-link.issue', $group), ['expires_in_days' => 7])
            ->assertRedirect();

        $link = $group->inviteLinks()->firstOrFail();
        $this->assertTrue($link->isUsable());

        $this->actingAs($newcomer)->get(route('join.show', $link->token))
            ->assertOk()
            ->assertSee($group->name);

        $this->actingAs($newcomer)->post(route('join.store', $link->token))
            ->assertRedirect(route('groups.show', $group));

        $this->assertTrue($group->fresh()->isActiveMember($newcomer));
        $this->assertSame(GroupRole::Member, $group->fresh()->roleOf($newcomer));
        $this->assertSame(1, $link->fresh()->used_count);
    }

    public function test_a_general_member_cannot_issue_a_link(): void
    {
        ['group' => $group, 'member' => $members] = $this->makeGroup();

        $this->actingAs($members[0])
            ->post(route('groups.invite-link.issue', $group), ['expires_in_days' => 7])
            ->assertForbidden();
    }

    public function test_issuing_a_new_link_revokes_the_previous_one(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $links = app(GroupInviteLinkService::class);

        $first = $links->issue($group, $highests[0]);
        $second = $links->issue($group->fresh(), $highests[0]);

        $this->assertTrue($first->fresh()->isRevoked());
        $this->assertTrue($second->isUsable());
        $this->assertSame($second->id, $links->currentFor($group->fresh())->id);
    }

    public function test_revoked_expired_and_used_up_links_are_refused(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $links = app(GroupInviteLinkService::class);
        $newcomer = User::factory()->create();

        $revoked = $links->issue($group, $highests[0]);
        $links->revoke($revoked, $highests[0]);
        $this->assertStringContainsString('無効', $revoked->fresh()->unusableReason());

        $expired = GroupInviteLink::create([
            'group_id' => $group->id,
            'created_by' => $highests[0]->id,
            'token' => GroupInviteLink::generateToken(),
            'expires_at' => now()->subDay(),
        ]);
        $this->assertStringContainsString('期限', $expired->unusableReason());

        $usedUp = GroupInviteLink::create([
            'group_id' => $group->id,
            'created_by' => $highests[0]->id,
            'token' => GroupInviteLink::generateToken(),
            'max_uses' => 1,
            'used_count' => 1,
        ]);
        $this->assertStringContainsString('上限', $usedUp->unusableReason());

        foreach ([$revoked, $expired, $usedUp] as $link) {
            try {
                $links->join($link->fresh(), $newcomer);
                $this->fail('使えないはずのリンクで参加できました。');
            } catch (BusinessRuleException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }

        $this->assertFalse($group->fresh()->isActiveMember($newcomer));
    }

    public function test_use_limit_is_enforced(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $links = app(GroupInviteLinkService::class);
        $link = $links->issue($group, $highests[0], maxUses: 1);

        $links->join($link->fresh(), User::factory()->create());

        $this->expectException(BusinessRuleException::class);
        $links->join($link->fresh(), User::factory()->create());
    }

    public function test_an_unregistered_visitor_is_guided_to_register_and_comes_back(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $link = app(GroupInviteLinkService::class)->issue($group, $highests[0]);

        // 未ログインでも招待の内容は見える
        $this->get(route('join.show', $link->token))
            ->assertOk()
            ->assertSee($group->name)
            ->assertSee('新規登録して参加する')
            ->assertSessionHas(JoinController::SESSION_KEY, $link->token);

        // 登録 → メール認証 → 招待に戻る
        $this->post(route('register.store'), [
            'user_id' => 'newbie01',
            'name' => '新入 太郎',
            'email' => 'newbie@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::firstWhere('user_id', 'newbie01');
        $this->assertNotNull($user);

        $user->markEmailAsVerified();

        $this->actingAs($user)->post(route('join.store', $link->token))
            ->assertRedirect(route('groups.show', $group));

        $this->assertTrue($group->fresh()->isActiveMember($user));
    }

    public function test_login_returns_to_the_pending_invitation(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $link = app(GroupInviteLinkService::class)->issue($group, $highests[0]);

        $user = User::factory()->create([
            'user_id' => 'guest001',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->get(route('join.show', $link->token))->assertOk();

        $this->post(route('login.store'), ['user_id' => 'guest001', 'password' => 'password123'])
            ->assertRedirect(route('join.show', $link->token));
    }

    public function test_the_passphrase_form_finds_the_link(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $link = app(GroupInviteLinkService::class)->issue($group, $highests[0]);

        $this->get(route('join.form'))->assertOk();

        $this->post(route('join.lookup'), ['token' => '  '.$link->token.'  '])
            ->assertRedirect(route('join.show', $link->token));

        $this->post(route('join.lookup'), ['token' => 'unknown-token'])
            ->assertRedirect(route('join.show', 'unknown-token'));

        $this->get(route('join.show', 'unknown-token'))
            ->assertRedirect(route('join.form'))
            ->assertSessionHasErrors('token');
    }

    public function test_joining_twice_is_refused(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $link = app(GroupInviteLinkService::class)->issue($group, $highests[0]);

        $this->actingAs($members[0])->get(route('join.show', $link->token))
            ->assertOk()
            ->assertSee('すでにこのグループのメンバーです');

        $this->actingAs($members[0])->post(route('join.store', $link->token))
            ->assertSessionHasErrors('link');
    }

    public function test_links_of_a_deleted_group_stop_working(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $link = app(GroupInviteLinkService::class)->issue($group, $highests[0]);

        $group->delete();

        $this->assertNull(app(GroupInviteLinkService::class)->findUsable($link->token));

        $this->get(route('join.show', $link->token))
            ->assertRedirect(route('join.form'));
    }
}
