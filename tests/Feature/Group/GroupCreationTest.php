<?php

namespace Tests\Feature\Group;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_joins_as_highest_responsible(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('groups.store'), [
            'name' => '冬コミ有志の会',
            'description' => 'テスト用グループ',
        ]);

        $group = Group::firstWhere('name', '冬コミ有志の会');

        $this->assertNotNull($group);
        $response->assertRedirect(route('groups.show', $group));

        $this->assertSame(GroupRole::HighestResponsible, $group->roleOf($user));
        $this->assertSame(1, $group->activeMemberCount());
    }

    public function test_name_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('groups.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Group::count());
    }

    public function test_name_is_limited_to_100_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('groups.store'), ['name' => str_repeat('あ', 101)])
            ->assertSessionHasErrors('name');
    }

    public function test_guest_cannot_create_group(): void
    {
        $this->post(route('groups.store'), ['name' => 'テスト'])
            ->assertRedirect(route('login'));
    }

    public function test_show_warns_when_group_has_no_responsible(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('groups.store'), ['name' => 'テスト']);

        $group = Group::firstWhere('name', 'テスト');

        $this->actingAs($user)
            ->get(route('groups.show', $group))
            ->assertOk()
            ->assertSee('責任者', false);
    }

    public function test_responsible_can_update_group_information(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['name' => '旧名称']);
        $group->members()->attach($user->id, ['role' => GroupRole::Responsible->value, 'joined_at' => now()]);

        $this->actingAs($user)
            ->patch(route('groups.update', $group), ['name' => '新名称', 'description' => '更新'])
            ->assertRedirect(route('groups.show', $group));

        $this->assertSame('新名称', $group->fresh()->name);
    }

    public function test_general_member_cannot_update_group_information(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['name' => '旧名称']);
        $group->members()->attach($user->id, ['role' => GroupRole::Member->value, 'joined_at' => now()]);

        $this->actingAs($user)
            ->patch(route('groups.update', $group), ['name' => '新名称'])
            ->assertForbidden();

        $this->assertSame('旧名称', $group->fresh()->name);
    }
}
