<?php

namespace App\Http\Controllers\Approval;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\Event;
use App\Policies\EventPolicy;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalService $approvals) {}

    /**
     * イベントの承認申請一覧。
     */
    public function index(Event $event): View
    {
        abort_unless(app(EventPolicy::class)->view(auth()->user(), $event), 403);

        $approvals = $event->approvals()
            ->with(['applicant', 'actions.actor'])
            ->latest('submitted_at')
            ->get();

        return view('approvals.index', [
            'event' => $event,
            'pending' => $approvals->where('status', ApprovalStatus::Pending)->values(),
            'history' => $approvals->reject(fn (Approval $a) => $a->isPending())->values(),
            'isApprover' => $this->approvals->isApprover(auth()->user(), $approvals->first() ?? new Approval(['group_id' => $event->group_id])),
            'approverCount' => $event->group->countActiveWithRole(\App\Enums\GroupRole::Responsible)
                + $event->group->countActiveWithRole(\App\Enums\GroupRole::HighestResponsible),
            'contentsUnlocked' => $this->approvals->contentsUnlocked($event),
            'canWithdraw' => fn (Approval $approval) => $this->approvals->canWithdraw(auth()->user(), $approval),
        ]);
    }

    /**
     * 承認・却下の投票。
     */
    public function vote(Approval $approval, string $decision): RedirectResponse
    {
        abort_unless($this->approvals->isApprover(auth()->user(), $approval), 403);
        abort_unless(in_array($decision, ['approve', 'reject'], true), 404);

        try {
            $this->approvals->vote($approval, auth()->user(), $decision === 'approve');
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', $decision === 'approve' ? '承認しました。' : '否決に投票しました。');
    }

    /**
     * 申請を取り下げる。
     */
    public function withdraw(Approval $approval): RedirectResponse
    {
        abort_unless($this->approvals->canWithdraw(auth()->user(), $approval), 403);

        try {
            $this->approvals->withdraw($approval, auth()->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', '申請を取り下げました。');
    }

    /**
     * 確定後の内容変更の解禁を申請する。
     */
    public function requestUnlock(Event $event): RedirectResponse
    {
        $role = $event->group->roleOf(auth()->user());
        abort_unless($role !== null && $role->isResponsibleOrAbove(), 403);

        try {
            $approval = $this->approvals->request($event, auth()->user(), ApprovalActionType::UnlockContents);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with(
            'status',
            $approval->status === ApprovalStatus::Pending
                ? '内容変更の解禁を申請しました。'
                : '内容変更が解禁されました。変更が終わったら再ロックしてください。'
        );
    }

    /**
     * 解禁を終了して再びロックする。
     */
    public function relock(Event $event): RedirectResponse
    {
        $role = $event->group->roleOf(auth()->user());
        abort_unless($role !== null && $role->isResponsibleOrAbove(), 403);
        abort_unless(
            in_array($event->status, [\App\Enums\EventStatus::Fixed, \App\Enums\EventStatus::Ongoing], true),
            403
        );

        $this->approvals->relock($event, auth()->user());

        return back()->with('status', '内容変更を再びロックしました。');
    }
}
