<?php

namespace Tests\Feature\Event;

use App\Enums\EventStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class EventStatusTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_responsible_can_start_accepting(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);

        $this->actingAs($responsibles[0])
            ->post(route('events.advance', $event))
            ->assertRedirect();

        $this->assertSame(EventStatus::Accepting, $event->fresh()->status);
    }

    public function test_general_member_cannot_advance(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);

        $this->actingAs($members[0])
            ->post(route('events.advance', $event))
            ->assertForbidden();
    }

    public function test_event_cannot_be_fixed_without_participants(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting);

        $this->actingAs($responsibles[0])
            ->post(route('events.advance', $event))
            ->assertSessionHasErrors('event');

        $this->assertSame(EventStatus::Accepting, $event->fresh()->status);
    }

    public function test_fixing_records_fixed_at(): void
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $members);

        // 最高責任者の承認は即時可決となる
        $this->actingAs($highests[0])->post(route('events.advance', $event))->assertRedirect();

        $fresh = $event->fresh();
        $this->assertSame(EventStatus::Fixed, $fresh->status);
        $this->assertNotNull($fresh->fixed_at);
        $this->assertTrue($fresh->isLocked());
    }

    public function test_responsible_alone_creates_a_pending_approval(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $members);

        $this->actingAs($responsibles[0])->post(route('events.advance', $event))->assertRedirect();

        // 責任者以上が2人（最高責任者1・責任者1）なので、1票では可決しない
        $this->assertSame(EventStatus::Accepting, $event->fresh()->status);
        $this->assertSame(1, \App\Models\Approval::count());
        $this->assertSame(\App\Enums\ApprovalStatus::Pending, \App\Models\Approval::first()->status);
    }

    public function test_only_highest_responsible_can_complete_settlement(): void
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Settling, $members);

        $this->actingAs($responsibles[0])->post(route('events.advance', $event))->assertForbidden();

        $this->actingAs($highests[0])->post(route('events.advance', $event))->assertRedirect();

        $this->assertSame(EventStatus::Completed, $event->fresh()->status);
    }

    public function test_completed_event_cannot_be_advanced(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Completed, $members);

        $this->actingAs($highests[0])->post(route('events.advance', $event))->assertForbidden();
    }

    public function test_highest_responsible_can_revert_status(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Fixed, $members);

        $this->actingAs($highests[0])
            ->post(route('events.revert', $event))
            ->assertRedirect();

        $fresh = $event->fresh();
        $this->assertSame(EventStatus::Accepting, $fresh->status);
        $this->assertNull($fresh->fixed_at);
    }

    public function test_responsible_cannot_revert_status(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Fixed, $members);

        $this->actingAs($responsibles[0])->post(route('events.revert', $event))->assertForbidden();
    }

    public function test_preparation_cannot_be_reverted(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0]);

        $this->actingAs($highests[0])->post(route('events.revert', $event))->assertForbidden();
    }

    public function test_full_status_chain(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation, $members);

        foreach ([EventStatus::Accepting, EventStatus::Fixed, EventStatus::Ongoing, EventStatus::Settling, EventStatus::Completed] as $expected) {
            $this->actingAs($highests[0])->post(route('events.advance', $event))->assertRedirect();
            $this->assertSame($expected, $event->fresh()->status);
        }
    }
}
