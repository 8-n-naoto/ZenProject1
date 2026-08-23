<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\GroupRole;
use App\Models\ChangeHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class ChangeHistoryTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_role_change_is_recorded(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();

        $this->actingAs($highests[0])->patch(route('groups.members.role.update', [$group, $members[0]]), [
            'role' => GroupRole::Responsible->value,
        ]);

        $history = ChangeHistory::where('action', 'member.role_changed')->first();

        $this->assertNotNull($history);
        $this->assertSame($highests[0]->id, $history->actor_user_id);
        $this->assertStringContainsString('責任者', $history->description());
    }

    public function test_status_change_is_recorded(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation, $members);

        $this->actingAs($highests[0])->post(route('events.advance', $event));

        $history = ChangeHistory::where('action', 'event.status_changed')->first();

        $this->assertNotNull($history);
        $this->assertStringContainsString('受付中', $history->description());
    }

    public function test_catalog_changes_are_recorded(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);

        $this->actingAs($responsibles[0])->post(route('circles.store', $event), [
            'display_name' => '夏空スタジオ',
        ]);

        $this->assertSame(1, ChangeHistory::where('action', 'circle.created')->count());
    }

    public function test_history_screen_renders(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation, $members);
        $this->actingAs($highests[0])->post(route('events.advance', $event));

        $this->actingAs($highests[0])
            ->get(route('histories.index', $event))
            ->assertOk()
            ->assertSee('受付中');
    }

    public function test_non_member_cannot_view_history(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation, $members);
        $outsider = \App\Models\User::factory()->create();

        $this->actingAs($outsider)->get(route('histories.index', $event))->assertForbidden();
    }
}
