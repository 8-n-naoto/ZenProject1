<?php

namespace Tests\Feature\Group;

use App\Enums\GroupRole;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class GroupInvitationTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_responsible_can_invite_a_user(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $target = User::factory()->create();

        $this->actingAs($responsibles[0])
            ->post(route('groups.invite', [$group, $target]))
            ->assertRedirect();

        $this->assertDatabaseHas('invitations', [
            'group_id' => $group->id,
            'invited_user_id' => $target->id,
            'invited_by' => $responsibles[0]->id,
            'status' => InvitationStatus::Pending->value,
        ]);
    }

    public function test_general_member_cannot_invite(): void
    {
        ['group' => $group, 'member' => $members] = $this->makeGroup();
        $target = User::factory()->create();

        $this->actingAs($members[0])
            ->post(route('groups.invite', [$group, $target]))
            ->assertForbidden();
    }

    public function test_cannot_invite_an_existing_member(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();

        $this->actingAs($highests[0])
            ->post(route('groups.invite', [$group, $members[0]]))
            ->assertSessionHasErrors('user');

        $this->assertSame(0, Invitation::count());
    }

    public function test_cannot_send_duplicate_pending_invitation(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $target = User::factory()->create();

        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $target]));
        $this->actingAs($highests[0])
            ->post(route('groups.invite', [$group, $target]))
            ->assertSessionHasErrors('user');

        $this->assertSame(1, Invitation::count());
    }

    public function test_search_excludes_members_and_invited_users(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $invited = User::factory()->create(['user_id' => 'zzinvited']);
        $free = User::factory()->create(['user_id' => 'zzfree']);
        $members[0]->update(['user_id' => 'zzmember']);

        // フラッシュメッセージを消費するためリダイレクトまで追跡する
        $this->actingAs($highests[0])->followingRedirects()->post(route('groups.invite', [$group, $invited]));

        $this->actingAs($highests[0])
            ->get(route('groups.search-users', ['group' => $group, 'q' => 'zz']))
            ->assertOk()
            ->assertSee('zzfree')
            ->assertDontSee('zzmember')
            ->assertDontSee('zzinvited');
    }

    public function test_invited_user_can_accept(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $target = User::factory()->create();

        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $target]));
        $invitation = Invitation::first();

        $this->actingAs($target)
            ->post(route('invitations.accept', $invitation))
            ->assertRedirect(route('groups.show', $group));

        $this->assertSame(GroupRole::Member, $group->fresh()->roleOf($target));
        $this->assertSame(InvitationStatus::Accepted, $invitation->fresh()->status);
    }

    public function test_invited_user_can_decline(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $target = User::factory()->create();

        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $target]));
        $invitation = Invitation::first();

        $this->actingAs($target)->post(route('invitations.decline', $invitation))->assertRedirect();

        $this->assertFalse($group->fresh()->isActiveMember($target));
        $this->assertSame(InvitationStatus::Declined, $invitation->fresh()->status);
    }

    public function test_other_user_cannot_respond_to_invitation(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $target = User::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $target]));
        $invitation = Invitation::first();

        $this->actingAs($stranger)->post(route('invitations.accept', $invitation))->assertForbidden();
        $this->actingAs($stranger)->post(route('invitations.decline', $invitation))->assertForbidden();
    }

    public function test_invitation_cannot_be_processed_twice(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $target = User::factory()->create();

        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $target]));
        $invitation = Invitation::first();

        $this->actingAs($target)->post(route('invitations.accept', $invitation));
        $this->actingAs($target)
            ->post(route('invitations.decline', $invitation))
            ->assertSessionHasErrors('invitation');
    }

    public function test_responsible_can_cancel_pending_invitation(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $target = User::factory()->create();

        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $target]));
        $invitation = Invitation::first();

        $this->actingAs($highests[0])
            ->delete(route('groups.invitations.cancel', [$group, $invitation]))
            ->assertRedirect();

        $this->assertSame(InvitationStatus::Cancelled, $invitation->fresh()->status);
    }

    public function test_general_member_cannot_cancel_invitation(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $target = User::factory()->create();

        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $target]));
        $invitation = Invitation::first();

        $this->actingAs($members[0])
            ->delete(route('groups.invitations.cancel', [$group, $invitation]))
            ->assertForbidden();
    }

    public function test_removed_member_can_rejoin_by_invitation(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $removed = $members[0];

        $this->actingAs($highests[0])->delete(route('groups.members.remove', [$group, $removed]));
        $this->assertFalse($group->fresh()->isActiveMember($removed));

        // 再招待できること
        $this->actingAs($highests[0])
            ->post(route('groups.invite', [$group, $removed]))
            ->assertRedirect();

        $invitation = Invitation::latest('id')->first();

        $this->actingAs($removed)->post(route('invitations.accept', $invitation))->assertRedirect();

        $fresh = $group->fresh();
        $this->assertTrue($fresh->isActiveMember($removed));
        $this->assertSame(GroupRole::Member, $fresh->roleOf($removed));
        $this->assertSame(1, $fresh->members()->where('users.id', $removed->id)->count());
    }

    public function test_rejoined_member_role_is_reset_to_general_member(): void
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles] = $this->makeGroup(['responsible' => 2]);
        $removed = $responsibles[0];

        $this->actingAs($highests[0])->delete(route('groups.members.remove', [$group, $removed]));
        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $removed]));

        $invitation = Invitation::latest('id')->first();
        $this->actingAs($removed)->post(route('invitations.accept', $invitation));

        $this->assertSame(GroupRole::Member, $group->fresh()->roleOf($removed));
    }
}
