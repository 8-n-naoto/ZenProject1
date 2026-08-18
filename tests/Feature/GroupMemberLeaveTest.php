<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupMemberLeaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_leave_group(): void
    {
        $highest = User::factory()->create();
        $responsible = User::factory()->create();
        $member = User::factory()->create();

        $group = Group::factory()->create();

        $group->members()->attach([
            $highest->id => [
                'role' => Group::ROLE_HIGHEST_RESPONSIBLE,
                'joined_at' => now(),
            ],
            $responsible->id => [
                'role' => Group::ROLE_RESPONSIBLE,
                'joined_at' => now(),
            ],
            $member->id => [
                'role' => Group::ROLE_MEMBER,
                'joined_at' => now(),
            ],
        ]);

        $response = $this->actingAs($member)
            ->delete(route('groups.members.leave', $group));

        $response->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'left_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_last_responsible_cannot_leave_group(): void
    {
        $highest = User::factory()->create();
        $responsible = User::factory()->create();

        $group = Group::factory()->create();

        $group->members()->attach([
            $highest->id => [
                'role' => Group::ROLE_HIGHEST_RESPONSIBLE,
                'joined_at' => now(),
            ],
            $responsible->id => [
                'role' => Group::ROLE_RESPONSIBLE,
                'joined_at' => now(),
            ],
        ]);

        $response = $this->actingAs($responsible)
            ->delete(route('groups.members.leave', $group));

        $response->assertRedirect();
        $response->assertSessionHasErrors('member');

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $responsible->id,
            'left_at' => null,
        ]);
    }

    public function test_last_highest_responsible_cannot_leave_group(): void
    {
        $highest = User::factory()->create();
        $responsible = User::factory()->create();

        $group = Group::factory()->create();

        $group->members()->attach([
            $highest->id => [
                'role' => Group::ROLE_HIGHEST_RESPONSIBLE,
                'joined_at' => now(),
            ],
            $responsible->id => [
                'role' => Group::ROLE_RESPONSIBLE,
                'joined_at' => now(),
            ],
        ]);

        $response = $this->actingAs($highest)
            ->delete(route('groups.members.leave', $group));

        $response->assertRedirect();
        $response->assertSessionHasErrors('member');

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $highest->id,
            'left_at' => null,
        ]);
    }

    public function test_responsible_can_remove_member(): void
    {
        $highest = User::factory()->create();
        $responsible = User::factory()->create();
        $member = User::factory()->create();

        $group = Group::factory()->create();

        $group->members()->attach([
            $highest->id => [
                'role' => Group::ROLE_HIGHEST_RESPONSIBLE,
                'joined_at' => now(),
            ],
            $responsible->id => [
                'role' => Group::ROLE_RESPONSIBLE,
                'joined_at' => now(),
            ],
            $member->id => [
                'role' => Group::ROLE_MEMBER,
                'joined_at' => now(),
            ],
        ]);

        $response = $this->actingAs($responsible)
            ->delete(route('groups.members.remove', [$group, $member]));

        $response->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'left_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_responsible_cannot_remove_responsible(): void
    {
        $highest = User::factory()->create();
        $responsible = User::factory()->create();
        $target = User::factory()->create();

        $group = Group::factory()->create();

        $group->members()->attach([
            $highest->id => [
                'role' => Group::ROLE_HIGHEST_RESPONSIBLE,
                'joined_at' => now(),
            ],
            $responsible->id => [
                'role' => Group::ROLE_RESPONSIBLE,
                'joined_at' => now(),
            ],
            $target->id => [
                'role' => Group::ROLE_RESPONSIBLE,
                'joined_at' => now(),
            ],
        ]);

        $response = $this->actingAs($responsible)
            ->delete(route('groups.members.remove', [$group, $target]));

        $response->assertForbidden();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $target->id,
            'left_at' => null,
        ]);
    }

    public function test_highest_responsible_can_remove_responsible(): void
    {
        $highest = User::factory()->create();
        $responsible = User::factory()->create();
        $member = User::factory()->create();

        $group = Group::factory()->create();

        $group->members()->attach([
            $highest->id => [
                'role' => Group::ROLE_HIGHEST_RESPONSIBLE,
                'joined_at' => now(),
            ],
            $responsible->id => [
                'role' => Group::ROLE_RESPONSIBLE,
                'joined_at' => now(),
            ],
            $member->id => [
                'role' => Group::ROLE_MEMBER,
                'joined_at' => now(),
            ],
        ]);

        $response = $this->actingAs($highest)
            ->delete(route('groups.members.remove', [$group, $responsible]));

        $response->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $responsible->id,
            'left_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_highest_responsible_can_remove_member(): void
    {
        $highest = User::factory()->create();
        $responsible = User::factory()->create();
        $member = User::factory()->create();

        $group = Group::factory()->create();

        $group->members()->attach([
            $highest->id => [
                'role' => Group::ROLE_HIGHEST_RESPONSIBLE,
                'joined_at' => now(),
            ],
            $responsible->id => [
                'role' => Group::ROLE_RESPONSIBLE,
                'joined_at' => now(),
            ],
            $member->id => [
                'role' => Group::ROLE_MEMBER,
                'joined_at' => now(),
            ],
        ]);

        $response = $this->actingAs($highest)
            ->delete(route('groups.members.remove', [$group, $member]));

        $response->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'left_at' => now()->toDateTimeString(),
        ]);
    }
}
