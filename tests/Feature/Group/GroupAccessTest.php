<?php

namespace Tests\Feature\Group;

use App\Enums\GroupRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 在籍していないユーザー（非メンバー・脱退済み・除名済み）が
 * グループの機能を一切利用できないことを検証する。
 */
class GroupAccessTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_non_member_cannot_view_group(): void
    {
        ['group' => $group] = $this->makeGroup();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('groups.show', $group))
            ->assertForbidden();
    }

    public function test_member_can_view_group(): void
    {
        ['group' => $group, 'member' => $members] = $this->makeGroup();

        $this->actingAs($members[0])
            ->get(route('groups.show', $group))
            ->assertOk();
    }

    public function test_removed_member_cannot_view_group(): void
    {
        ['group' => $group, 'member' => $members] = $this->makeGroup();
        $this->markAsLeft($group, $members[0]);

        $this->actingAs($members[0])
            ->get(route('groups.show', $group))
            ->assertForbidden();
    }

    public function test_removed_responsible_cannot_invite(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup(['responsible' => 2]);
        $this->markAsLeft($group, $responsibles[0]);
        $target = User::factory()->create();

        $this->actingAs($responsibles[0])
            ->get(route('groups.search-users', $group))
            ->assertForbidden();

        $this->actingAs($responsibles[0])
            ->post(route('groups.invite', [$group, $target]))
            ->assertForbidden();
    }

    public function test_removed_highest_responsible_cannot_change_roles(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup(['highest' => 2]);
        $this->markAsLeft($group, $highests[0]);

        $this->actingAs($highests[0])
            ->patch(route('groups.members.role.update', [$group, $members[0]]), [
                'role' => GroupRole::Responsible->value,
            ])
            ->assertForbidden();

        $this->assertSame(GroupRole::Member, $group->fresh()->roleOf($members[0]));
    }

    public function test_removed_member_cannot_remove_others(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup(['highest' => 2, 'member' => 2]);
        $this->markAsLeft($group, $highests[0]);

        $this->actingAs($highests[0])
            ->delete(route('groups.members.remove', [$group, $members[0]]))
            ->assertForbidden();
    }

    public function test_removed_member_cannot_leave_again(): void
    {
        ['group' => $group, 'member' => $members] = $this->makeGroup();
        $this->markAsLeft($group, $members[0]);

        $this->actingAs($members[0])
            ->delete(route('groups.members.leave', $group))
            ->assertForbidden();
    }

    public function test_removed_member_is_not_counted_as_member(): void
    {
        ['group' => $group, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $this->markAsLeft($group, $members[0]);

        $this->assertSame(3, $group->activeMemberCount());
        $this->assertFalse($group->isActiveMember($members[0]));
        $this->assertTrue($group->isFormerMember($members[0]));
    }

    public function test_group_list_excludes_groups_the_user_left(): void
    {
        ['group' => $group, 'member' => $members] = $this->makeGroup();
        $this->markAsLeft($group, $members[0]);

        $this->actingAs($members[0])
            ->get(route('groups.index'))
            ->assertOk()
            ->assertDontSee($group->name, false);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        ['group' => $group] = $this->makeGroup();

        $this->get(route('groups.show', $group))->assertRedirect(route('login'));
        $this->get(route('groups.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('invitations.index'))->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }
}
