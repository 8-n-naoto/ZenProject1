<?php

namespace Tests\Feature\Approval;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\EventStatus;
use App\Enums\GroupRole;
use App\Services\ApprovalService;
use App\Services\GroupMemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 承認申請が「承認待ちのまま二度と動かない」状態にならないことを確認する。
 */
class ApprovalDeadlockTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private function acceptingEvent(array $counts = []): array
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles, 'member' => $members]
            = $this->makeGroup(array_merge(['highest' => 1, 'responsible' => 1, 'member' => 1], $counts));

        $participants = array_merge($highests, $responsibles, $members);
        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting, $participants);
        $this->makeCatalog($event, '夏空スタジオ', [['name' => '新刊', 'price' => 1000]]);

        return compact('group', 'highests', 'responsibles', 'members', 'event');
    }

    public function test_rejection_by_the_highest_responsible_settles_a_tie_immediately(): void
    {
        ['highests' => $highests, 'responsibles' => $responsibles, 'event' => $event] = $this->acceptingEvent();

        $approvals = app(ApprovalService::class);
        $approval = $approvals->request($event, $responsibles[0], ApprovalActionType::FixEvent);
        $this->assertSame(ApprovalStatus::Pending, $approval->status);

        $approval = $approvals->vote($approval, $highests[0], false);

        $this->assertSame(ApprovalStatus::Rejected, $approval->status);
        $this->assertSame(EventStatus::Accepting, $event->fresh()->status);
    }

    public function test_a_stuck_request_can_be_withdrawn_and_retried(): void
    {
        ['highests' => $highests, 'responsibles' => $responsibles, 'event' => $event] = $this->acceptingEvent(['responsible' => 3]);

        $approvals = app(ApprovalService::class);
        $approval = $approvals->request($event, $responsibles[0], ApprovalActionType::FixEvent);
        $approvals->vote($approval, $responsibles[1], false);
        $approvals->vote($approval->fresh(), $responsibles[2], false);

        // 2賛成 / 2反対では決まらない（承認者4人・必要3票）
        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);

        // 申請者が取り下げられる
        $this->actingAs($responsibles[0])
            ->delete(route('approvals.withdraw', $approval))
            ->assertRedirect();

        $this->assertSame(ApprovalStatus::Withdrawn, $approval->fresh()->status);

        // 取り下げたので、同じ申請をやり直せる
        $retry = $approvals->request($event->fresh(), $responsibles[0], ApprovalActionType::FixEvent);
        $this->assertContains($retry->status, [ApprovalStatus::Pending, ApprovalStatus::Applied]);
    }

    public function test_only_the_applicant_or_highest_responsible_can_withdraw(): void
    {
        ['highests' => $highests, 'responsibles' => $responsibles, 'members' => $members, 'event' => $event]
            = $this->acceptingEvent(['responsible' => 3]);

        $approval = app(ApprovalService::class)->request($event, $responsibles[0], ApprovalActionType::FixEvent);

        $this->actingAs($members[0])->delete(route('approvals.withdraw', $approval))->assertForbidden();
        $this->actingAs($responsibles[1])->delete(route('approvals.withdraw', $approval))->assertForbidden();
        $this->actingAs($highests[0])->delete(route('approvals.withdraw', $approval))->assertRedirect();
    }

    public function test_votes_from_removed_approvers_are_not_counted(): void
    {
        ['group' => $group, 'highests' => $highests, 'responsibles' => $responsibles, 'event' => $event]
            = $this->acceptingEvent(['responsible' => 2]);

        $approvals = app(ApprovalService::class);
        // 承認者は3人（最高責任者1 + 責任者2）、必要3票…ではなく過半数の2票
        $approval = $approvals->request($event, $responsibles[0], ApprovalActionType::FixEvent);
        $this->assertSame(ApprovalStatus::Pending, $approval->status);

        // 申請した責任者を降格させると、その1票は無効になる
        app(GroupMemberService::class)->changeRole($group, $responsibles[0], GroupRole::Member);

        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);
        $this->assertSame(EventStatus::Accepting, $event->fresh()->status);
    }

    public function test_pending_requests_are_reevaluated_when_approvers_leave(): void
    {
        ['group' => $group, 'highests' => $highests, 'responsibles' => $responsibles, 'event' => $event]
            = $this->acceptingEvent(['responsible' => 4]);

        $approvals = app(ApprovalService::class);
        // 承認者5人・必要3票。責任者2人が賛成した時点では未決
        $approval = $approvals->request($event, $responsibles[0], ApprovalActionType::FixEvent);
        $approvals->vote($approval, $responsibles[1], true);
        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);

        // 投票していない責任者2人が抜けると、承認者3人・必要2票になり可決する
        $members = app(GroupMemberService::class);
        $members->remove($group, $responsibles[2]);
        $members->remove($group->fresh(), $responsibles[3]);

        $this->assertSame(ApprovalStatus::Applied, $approval->fresh()->status);
        $this->assertSame(EventStatus::Fixed, $event->fresh()->status);
    }
}
