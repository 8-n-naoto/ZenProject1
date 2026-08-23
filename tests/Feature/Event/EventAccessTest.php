<?php

namespace Tests\Feature\Event;

use App\Enums\EventStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class EventAccessTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_group_member_can_view_event_before_fixing(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting);

        $this->actingAs($members[0])->get(route('events.show', $event))->assertOk();
    }

    public function test_non_participant_cannot_view_event_after_fixing(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Fixed, [$responsibles[0]]);

        $this->actingAs($members[0])->get(route('events.show', $event))->assertForbidden();
    }

    public function test_participant_can_view_event_after_fixing(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Fixed, $members);

        $this->actingAs($members[0])->get(route('events.show', $event))->assertOk();
    }

    public function test_responsible_can_always_view_event(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Completed, $members);

        $this->actingAs($responsibles[0])->get(route('events.show', $event))->assertOk();
    }

    public function test_non_group_member_cannot_view_event(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('events.show', $event))->assertForbidden();
        $this->actingAs($outsider)->get(route('events.index', $group))->assertForbidden();
    }

    public function test_removed_member_cannot_view_event(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting);
        $this->markAsLeft($group, $members[0]);

        $this->actingAs($members[0])->get(route('events.show', $event))->assertForbidden();
        $this->actingAs($members[0])->get(route('events.index', $group))->assertForbidden();
    }

    public function test_completed_event_is_read_only_for_non_highest_responsible(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Completed, $members);

        $this->actingAs($responsibles[0])->get(route('events.edit', $event))->assertForbidden();
        $this->actingAs($responsibles[0])->post(route('events.advance', $event))->assertForbidden();
        $this->actingAs($members[0])->post(route('events.join', $event))->assertForbidden();
    }

    public function test_event_screens_render(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $members, 2);

        $this->actingAs($responsibles[0])->get(route('events.index', $group))->assertOk()->assertSee($event->name);
        $this->actingAs($responsibles[0])->get(route('events.create', $group))->assertOk();
        $this->actingAs($responsibles[0])->get(route('events.show', $event))->assertOk();
        $this->actingAs($responsibles[0])->get(route('events.edit', $event))->assertOk();
        $this->actingAs($responsibles[0])->get(route('events.members.index', $event))->assertOk();
    }

    public function test_dashboard_lists_active_events(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $members);

        $this->actingAs($members[0])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($event->name);
    }
}
