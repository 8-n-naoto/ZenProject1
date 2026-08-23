<?php

namespace Tests\Feature\Approval;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\EventStatus;
use App\Models\Approval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class ApprovalTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    /**
     * 最高責任者1・責任者2 のグループ（承認に必要な票数は2）
     */
    private function scenario(EventStatus $status = EventStatus::Accepting): array
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['responsible' => 2]);
        $participants = array_merge($highests, $responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], $status, $participants);

        return compact('group', 'highests', 'responsibles', 'members', 'event');
    }

    public function test_responsible_request_creates_pending_approval(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $this->actingAs($responsibles[0])->post(route('events.advance', $event))->assertRedirect();

        $approval = Approval::first();

        $this->assertSame(ApprovalStatus::Pending, $approval->status);
        $this->assertSame(ApprovalActionType::FixEvent, $approval->action_type);
        $this->assertSame(1, $approval->approvalCount());
        $this->assertSame(EventStatus::Accepting, $event->fresh()->status);
    }

    public function test_second_responsible_approval_passes_the_vote(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('events.advance', $event));
        $approval = Approval::first();

        $this->actingAs($responsibles[1])
            ->post(route('approvals.vote', [$approval, 'approve']))
            ->assertRedirect();

        $this->assertSame(ApprovalStatus::Applied, $approval->fresh()->status);
        $this->assertSame(EventStatus::Fixed, $event->fresh()->status);
    }

    public function test_highest_responsible_approval_passes_immediately(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'highests' => $highests] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('events.advance', $event));
        $approval = Approval::first();

        $this->actingAs($highests[0])->post(route('approvals.vote', [$approval, 'approve']))->assertRedirect();

        $this->assertSame(EventStatus::Fixed, $event->fresh()->status);
    }

    public function test_majority_rejection_rejects_the_request(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'highests' => $highests] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('events.advance', $event));
        $approval = Approval::first();

        $this->actingAs($responsibles[1])->post(route('approvals.vote', [$approval, 'reject']));
        $this->actingAs($highests[0])->post(route('approvals.vote', [$approval, 'reject']));

        $this->assertSame(ApprovalStatus::Rejected, $approval->fresh()->status);
        $this->assertSame(EventStatus::Accepting, $event->fresh()->status);
    }

    public function test_general_member_cannot_vote(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('events.advance', $event));
        $approval = Approval::first();

        $this->actingAs($members[0])
            ->post(route('approvals.vote', [$approval, 'approve']))
            ->assertForbidden();
    }

    public function test_cannot_vote_twice(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('events.advance', $event));
        $approval = Approval::first();

        $this->actingAs($responsibles[0])
            ->post(route('approvals.vote', [$approval, 'approve']))
            ->assertSessionHasErrors('approval');
    }

    public function test_duplicate_request_is_rejected(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('events.advance', $event));

        $this->actingAs($responsibles[1])
            ->post(route('events.advance', $event))
            ->assertSessionHasErrors('approval');

        $this->assertSame(1, Approval::count());
    }

    public function test_unlock_allows_editing_after_fixing(): void
    {
        ['group' => $group, 'event' => $event, 'highests' => $highests, 'responsibles' => $responsibles] = $this->scenario();
        ['circle' => $circle] = $this->makeCatalog($event);
        $event->update(['status' => EventStatus::Fixed, 'fixed_at' => now()]);

        // 解禁前は編集できない
        $this->actingAs($responsibles[0])
            ->post(route('products.store', $circle), ['name' => '追加商品', 'price' => 500])
            ->assertForbidden();

        // 最高責任者が申請すると即時可決
        $this->actingAs($highests[0])
            ->post(route('approvals.unlock', $event->fresh()))
            ->assertRedirect();

        $this->assertSame(ApprovalStatus::Approved, Approval::first()->status);

        $this->actingAs($responsibles[0])
            ->post(route('products.store', $circle->fresh()), ['name' => '追加商品', 'price' => 500])
            ->assertRedirect();

        // 再ロックすると編集できなくなる
        $this->actingAs($highests[0])->post(route('approvals.relock', $event->fresh()))->assertRedirect();

        $this->actingAs($responsibles[0])
            ->post(route('products.store', $circle->fresh()), ['name' => 'さらに追加', 'price' => 500])
            ->assertForbidden();
    }

    public function test_unlock_cannot_be_requested_before_fixing(): void
    {
        ['event' => $event, 'highests' => $highests] = $this->scenario();

        $this->actingAs($highests[0])
            ->post(route('approvals.unlock', $event))
            ->assertSessionHasErrors('approval');
    }

    public function test_reopen_requires_approval(): void
    {
        ['group' => $group, 'event' => $event, 'highests' => $highests] = $this->scenario();
        $event->update(['status' => EventStatus::Completed, 'fixed_at' => now()]);

        $this->actingAs($highests[0])->post(route('events.revert', $event->fresh()))->assertRedirect();

        $this->assertSame(ApprovalActionType::ReopenEvent, Approval::first()->action_type);
        $this->assertSame(EventStatus::Settling, $event->fresh()->status);
    }

    public function test_approval_screen_renders(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('events.advance', $event));

        $this->actingAs($responsibles[1])
            ->get(route('approvals.index', $event))
            ->assertOk()
            ->assertSee('イベントの確定');
    }
}
