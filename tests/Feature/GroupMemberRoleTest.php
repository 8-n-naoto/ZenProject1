<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupMemberRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_highest_responsible_can_promote_member_to_responsible(): void
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
            ->patch(route('groups.members.role.update', [$group, $member]), [
                'role' => Group::ROLE_RESPONSIBLE,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => Group::ROLE_RESPONSIBLE,
        ]);
    }

    public function test_highest_responsible_can_promote_member_to_highest_responsible(): void
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
            ->patch(route('groups.members.role.update', [$group, $member]), [
                'role' => Group::ROLE_HIGHEST_RESPONSIBLE,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => Group::ROLE_HIGHEST_RESPONSIBLE,
        ]);
    }

    public function test_last_highest_responsible_cannot_be_demoted(): void
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
            ->patch(route('groups.members.role.update', [$group, $highest]), [
                'role' => Group::ROLE_RESPONSIBLE,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('member');

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $highest->id,
            'role' => Group::ROLE_HIGHEST_RESPONSIBLE,
        ]);
    }

    public function test_last_responsible_cannot_be_demoted(): void
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
            ->patch(route('groups.members.role.update', [$group, $responsible]), [
                'role' => Group::ROLE_MEMBER,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('member');

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $responsible->id,
            'role' => Group::ROLE_RESPONSIBLE,
        ]);
    }

    public function test_responsible_cannot_change_member_roles(): void
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
            ->patch(route('groups.members.role.update', [$group, $member]), [
                'role' => Group::ROLE_RESPONSIBLE,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => Group::ROLE_MEMBER,
        ]);
    }
}
