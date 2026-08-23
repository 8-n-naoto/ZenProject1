<?php

namespace Tests\Feature\Event;

use App\Enums\EventStatus;
use App\Enums\GroupRole;
use App\Models\Event;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class EventCreationTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'コミックマーケット105',
            'venue_name' => '東京ビッグサイト',
            'venue_address' => '東京都江東区有明3-11-1',
            'description' => '集合は西1ホール前',
            'days' => [
                ['event_date' => now()->addDays(30)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '16:00'],
                ['event_date' => now()->addDays(31)->toDateString(), 'starts_at' => '10:30', 'ends_at' => '16:00'],
            ],
        ], $overrides);
    }

    public function test_responsible_can_create_event(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();

        $this->actingAs($responsibles[0])
            ->post(route('events.store', $group), $this->payload())
            ->assertRedirect();

        $event = Event::first();

        $this->assertNotNull($event);
        $this->assertSame(EventStatus::Preparation, $event->status);
        $this->assertSame(2, $event->days()->count());
        $this->assertSame('10:00', $event->starts_at->format('H:i'));
        $this->assertSame('16:00', $event->ends_at->format('H:i'));
        $this->assertSame(now()->addDays(31)->toDateString(), $event->ends_at->toDateString());
        $this->assertNull($event->fixed_at);
    }

    public function test_general_member_cannot_create_event(): void
    {
        ['group' => $group, 'member' => $members] = $this->makeGroup();

        $this->actingAs($members[0])
            ->post(route('events.store', $group), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, Event::count());
    }

    public function test_non_member_cannot_create_event(): void
    {
        ['group' => $group] = $this->makeGroup();
        $outsider = \App\Models\User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('events.store', $group), $this->payload())
            ->assertForbidden();
    }

    public function test_group_without_responsible_cannot_create_event(): void
    {
        $group = Group::factory()->create();
        $highest = \App\Models\User::factory()->create();
        $group->members()->attach($highest->id, [
            'role' => GroupRole::HighestResponsible->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($highest)
            ->post(route('events.store', $group), $this->payload())
            ->assertSessionHasErrors('event');

        $this->assertSame(0, Event::count());
    }

    public function test_at_least_one_day_is_required(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();

        $this->actingAs($responsibles[0])
            ->post(route('events.store', $group), $this->payload(['days' => []]))
            ->assertSessionHasErrors('days');
    }

    public function test_duplicate_dates_are_rejected(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $date = now()->addDays(30)->toDateString();

        $this->actingAs($responsibles[0])
            ->post(route('events.store', $group), $this->payload(['days' => [
                ['event_date' => $date, 'starts_at' => '10:00', 'ends_at' => '16:00'],
                ['event_date' => $date, 'starts_at' => '11:00', 'ends_at' => '17:00'],
            ]]))
            ->assertSessionHasErrors('days');
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();

        $this->actingAs($responsibles[0])
            ->post(route('events.store', $group), $this->payload(['days' => [
                ['event_date' => now()->addDays(30)->toDateString(), 'starts_at' => '16:00', 'ends_at' => '10:00'],
            ]]))
            ->assertSessionHasErrors('days.0.ends_at');
    }

    public function test_name_is_required(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();

        $this->actingAs($responsibles[0])
            ->post(route('events.store', $group), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');
    }

    public function test_event_can_be_updated_before_fixing(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);

        $this->actingAs($responsibles[0])
            ->patch(route('events.update', $event), $this->payload(['name' => '変更後']))
            ->assertRedirect(route('events.show', $event));

        $this->assertSame('変更後', $event->fresh()->name);
        $this->assertSame(2, $event->fresh()->days()->count());
    }

    public function test_event_cannot_be_updated_after_fixing(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Fixed, $responsibles);

        $this->actingAs($responsibles[0])
            ->patch(route('events.update', $event), $this->payload(['name' => '変更後']))
            ->assertForbidden();
    }

    public function test_only_highest_responsible_can_delete_event_in_preparation(): void
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);

        $this->actingAs($responsibles[0])->delete(route('events.destroy', $event))->assertForbidden();

        $this->actingAs($highests[0])
            ->delete(route('events.destroy', $event))
            ->assertRedirect(route('events.index', $group));

        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_event_cannot_be_deleted_after_accepting_started(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting);

        $this->actingAs($highests[0])->delete(route('events.destroy', $event))->assertForbidden();
    }
}
