<?php

namespace Tests\Feature\Event;

use App\Enums\EventStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class EventParticipationTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_member_can_join_while_accepting(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting);

        $this->actingAs($members[0])
            ->post(route('events.join', $event))
            ->assertRedirect();

        $this->assertTrue($event->fresh()->isParticipant($members[0]));
    }

    public function test_cannot_join_during_preparation(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Preparation);

        $this->actingAs($members[0])->post(route('events.join', $event))->assertForbidden();
    }

    public function test_cannot_join_after_fixing(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Fixed, [$responsibles[0]]);

        $this->actingAs($members[0])->post(route('events.join', $event))->assertForbidden();
    }

    public function test_non_group_member_cannot_join(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post(route('events.join', $event))->assertForbidden();
    }

    public function test_removed_group_member_cannot_join(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting);
        $this->markAsLeft($group, $members[0]);

        $this->actingAs($members[0])->post(route('events.join', $event))->assertForbidden();
    }

    public function test_participant_can_leave_while_accepting(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $members);

        $this->actingAs($members[0])
            ->delete(route('events.leave', $event))
            ->assertRedirect();

        $this->assertFalse($event->fresh()->isParticipant($members[0]));
    }

    public function test_participant_cannot_leave_after_fixing(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Fixed, $members);

        $this->actingAs($members[0])->delete(route('events.leave', $event))->assertForbidden();
    }

    public function test_responsible_can_add_and_remove_participants(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting);

        $this->actingAs($responsibles[0])
            ->post(route('events.members.add', [$event, $members[0]]))
            ->assertRedirect();
        $this->assertTrue($event->fresh()->isParticipant($members[0]));

        $this->actingAs($responsibles[0])
            ->delete(route('events.members.remove', [$event, $members[0]]))
            ->assertRedirect();
        $this->assertFalse($event->fresh()->isParticipant($members[0]));
    }

    public function test_general_member_cannot_manage_participants(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting);

        $this->actingAs($members[0])
            ->post(route('events.members.add', [$event, $members[1]]))
            ->assertForbidden();

        $this->actingAs($members[0])
            ->get(route('events.members.index', $event))
            ->assertForbidden();
    }

    public function test_responsible_cannot_add_non_group_member(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting);
        $outsider = User::factory()->create();

        $this->actingAs($responsibles[0])
            ->post(route('events.members.add', [$event, $outsider]))
            ->assertForbidden();
    }

    public function test_duplicate_join_is_rejected(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $members);

        $this->actingAs($members[0])->post(route('events.join', $event))->assertForbidden();
    }
}
