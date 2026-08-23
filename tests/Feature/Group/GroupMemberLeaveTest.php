<?php

namespace Tests\Feature\Group;

use App\Enums\GroupRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class GroupMemberLeaveTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_member_can_leave_group(): void
    {
        ['group' => $group, 'member' => $members] = $this->makeGroup();

        $this->actingAs($members[0])
            ->delete(route('groups.members.leave', $group))
            ->assertRedirect(route('groups.index'));

        $this->assertFalse($group->fresh()->isActiveMember($members[0]));
    }

    public function test_last_responsible_cannot_leave(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();

        $this->actingAs($responsibles[0])
            ->delete(route('groups.members.leave', $group))
            ->assertSessionHasErrors('member');

        $this->assertTrue($group->fresh()->isActiveMember($responsibles[0]));
    }

    public function test_last_highest_responsible_cannot_leave(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();

        $this->actingAs($highests[0])
            ->delete(route('groups.members.leave', $group))
            ->assertSessionHasErrors('member');

        $this->assertTrue($group->fresh()->isActiveMember($highests[0]));
    }

    public function test_responsible_can_remove_general_member(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();

        $this->actingAs($responsibles[0])
            ->delete(route('groups.members.remove', [$group, $members[0]]))
            ->assertRedirect();

        $this->assertFalse($group->fresh()->isActiveMember($members[0]));
    }

    public function test_responsible_cannot_remove_another_responsible(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup(['responsible' => 2]);

        $this->actingAs($responsibles[0])
            ->delete(route('groups.members.remove', [$group, $responsibles[1]]))
            ->assertForbidden();

        $this->assertTrue($group->fresh()->isActiveMember($responsibles[1]));
    }

    public function test_highest_responsible_can_remove_responsible_when_another_exists(): void
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles] = $this->makeGroup(['responsible' => 2]);

        $this->actingAs($highests[0])
            ->delete(route('groups.members.remove', [$group, $responsibles[0]]))
            ->assertRedirect();

        $this->assertFalse($group->fresh()->isActiveMember($responsibles[0]));
    }

    public function test_last_responsible_cannot_be_removed(): void
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles] = $this->makeGroup();

        $this->actingAs($highests[0])
            ->delete(route('groups.members.remove', [$group, $responsibles[0]]))
            ->assertSessionHasErrors('member');

        $this->assertTrue($group->fresh()->isActiveMember($responsibles[0]));
    }

    public function test_last_highest_responsible_cannot_be_removed(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup(['highest' => 2]);
        // 1人を先に除名 → 残り1人は除名できない
        $this->actingAs($highests[0])
            ->delete(route('groups.members.remove', [$group, $highests[1]]))
            ->assertRedirect();

        $this->actingAs($highests[0])
            ->delete(route('groups.members.remove', [$group, $highests[0]]))
            ->assertForbidden();

        $this->assertTrue($group->fresh()->isActiveMember($highests[0]));
    }

    public function test_general_member_cannot_remove_others(): void
    {
        ['group' => $group, 'member' => $members] = $this->makeGroup(['member' => 2]);

        $this->actingAs($members[0])
            ->delete(route('groups.members.remove', [$group, $members[1]]))
            ->assertForbidden();
    }

    public function test_removing_a_non_member_fails(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $outsider = \App\Models\User::factory()->create();

        $this->actingAs($highests[0])
            ->delete(route('groups.members.remove', [$group, $outsider]))
            ->assertForbidden();
    }

    public function test_role_is_preserved_in_history_after_leaving(): void
    {
        ['group' => $group, 'member' => $members] = $this->makeGroup();

        $this->actingAs($members[0])->delete(route('groups.members.leave', $group));

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $members[0]->id,
            'role' => GroupRole::Member->value,
        ]);
        $this->assertNotNull(
            $group->members()->where('users.id', $members[0]->id)->first()->pivot->left_at
        );
    }
}
