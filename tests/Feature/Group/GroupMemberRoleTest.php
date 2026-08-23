<?php

namespace Tests\Feature\Group;

use App\Enums\GroupRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class GroupMemberRoleTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_highest_responsible_can_promote_member_to_responsible(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();

        $this->actingAs($highests[0])
            ->patch(route('groups.members.role.update', [$group, $members[0]]), [
                'role' => GroupRole::Responsible->value,
            ])
            ->assertRedirect();

        $this->assertSame(GroupRole::Responsible, $group->fresh()->roleOf($members[0]));
    }

    public function test_multiple_highest_responsibles_are_allowed(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();

        $this->actingAs($highests[0])
            ->patch(route('groups.members.role.update', [$group, $members[0]]), [
                'role' => GroupRole::HighestResponsible->value,
            ])
            ->assertRedirect();

        $this->assertSame(2, $group->fresh()->countActiveWithRole(GroupRole::HighestResponsible));
    }

    public function test_last_highest_responsible_cannot_be_demoted(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();

        $this->actingAs($highests[0])
            ->patch(route('groups.members.role.update', [$group, $highests[0]]), [
                'role' => GroupRole::Responsible->value,
            ])
            ->assertSessionHasErrors('member');

        $this->assertSame(GroupRole::HighestResponsible, $group->fresh()->roleOf($highests[0]));
    }

    public function test_last_responsible_cannot_be_demoted(): void
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles] = $this->makeGroup();

        $this->actingAs($highests[0])
            ->patch(route('groups.members.role.update', [$group, $responsibles[0]]), [
                'role' => GroupRole::Member->value,
            ])
            ->assertSessionHasErrors('member');

        $this->assertSame(GroupRole::Responsible, $group->fresh()->roleOf($responsibles[0]));
    }

    public function test_responsible_can_be_demoted_when_another_exists(): void
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles] = $this->makeGroup(['responsible' => 2]);

        $this->actingAs($highests[0])
            ->patch(route('groups.members.role.update', [$group, $responsibles[0]]), [
                'role' => GroupRole::Member->value,
            ])
            ->assertRedirect();

        $this->assertSame(GroupRole::Member, $group->fresh()->roleOf($responsibles[0]));
    }

    public function test_left_members_are_not_counted_when_checking_the_minimum(): void
    {
        // 責任者が2人いるが1人は除名済み → 残り1人は降格できない
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles] = $this->makeGroup(['responsible' => 2]);
        $this->markAsLeft($group, $responsibles[1]);

        $this->actingAs($highests[0])
            ->patch(route('groups.members.role.update', [$group, $responsibles[0]]), [
                'role' => GroupRole::Member->value,
            ])
            ->assertSessionHasErrors('member');

        $this->assertSame(GroupRole::Responsible, $group->fresh()->roleOf($responsibles[0]));
    }

    public function test_responsible_cannot_change_roles(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();

        $this->actingAs($responsibles[0])
            ->patch(route('groups.members.role.update', [$group, $members[0]]), [
                'role' => GroupRole::Responsible->value,
            ])
            ->assertForbidden();
    }

    public function test_invalid_role_is_rejected(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();

        $this->actingAs($highests[0])
            ->patch(route('groups.members.role.update', [$group, $members[0]]), ['role' => '管理人'])
            ->assertSessionHasErrors('role');
    }

    public function test_changing_to_the_same_role_is_a_no_op(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();

        $this->actingAs($highests[0])
            ->patch(route('groups.members.role.update', [$group, $members[0]]), [
                'role' => GroupRole::Member->value,
            ])
            ->assertSessionHas('status');
    }
}
