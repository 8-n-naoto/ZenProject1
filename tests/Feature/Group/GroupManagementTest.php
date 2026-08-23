<?php

namespace Tests\Feature\Group;

use App\Enums\GroupRole;
use App\Enums\InvitationStatus;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class GroupManagementTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_highest_responsible_can_delete_group(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();

        $this->actingAs($highests[0])
            ->delete(route('groups.destroy', $group))
            ->assertRedirect(route('groups.index'));

        $this->assertSoftDeleted('groups', ['id' => $group->id]);

        // 残っていたメンバーは在籍解除される
        $this->assertDatabaseMissing('group_members', [
            'group_id' => $group->id,
            'user_id' => $members[0]->id,
            'left_at' => null,
        ]);
    }

    public function test_responsible_cannot_delete_group(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();

        $this->actingAs($responsibles[0])
            ->delete(route('groups.destroy', $group))
            ->assertForbidden();

        $this->assertDatabaseHas('groups', ['id' => $group->id, 'deleted_at' => null]);
    }

    public function test_deleting_group_cancels_pending_invitations(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $target = User::factory()->create();
        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $target]));

        $this->actingAs($highests[0])->delete(route('groups.destroy', $group));

        $this->assertSame(InvitationStatus::Cancelled, Invitation::first()->status);
    }

    public function test_group_image_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();

        $this->actingAs($highests[0])->patch(route('groups.update', $group), [
            'name' => $group->name,
            'image' => UploadedFile::fake()->image('icon.png', 200, 200),
        ])->assertRedirect();

        $path = $group->fresh()->image_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($highests[0])->patch(route('groups.update', $group), [
            'name' => $group->name,
            'remove_image' => '1',
        ])->assertRedirect();

        $this->assertNull($group->fresh()->image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();

        $this->actingAs($highests[0])->patch(route('groups.update', $group), [
            'name' => $group->name,
            'image' => UploadedFile::fake()->create('note.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('image');
    }

    public function test_deleted_group_is_not_listed(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $this->actingAs($highests[0])->delete(route('groups.destroy', $group));

        $this->actingAs($highests[0])
            ->get(route('groups.index'))
            ->assertOk()
            ->assertDontSee($group->name, false);
    }

    public function test_group_helpers_count_only_active_members(): void
    {
        ['group' => $group, 'member' => $members] = $this->makeGroup(['member' => 3]);
        $this->markAsLeft($group, $members[0]);
        $this->markAsLeft($group, $members[1]);

        $fresh = Group::find($group->id);

        $this->assertSame(3, $fresh->activeMemberCount());
        $this->assertSame(1, $fresh->countActiveWithRole(GroupRole::HighestResponsible));
        $this->assertTrue($fresh->isLastOfRole(GroupRole::HighestResponsible));
        $this->assertFalse($fresh->isLastOfRole(GroupRole::Member));
    }
}
