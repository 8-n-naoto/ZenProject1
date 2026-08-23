<?php

namespace App\Services;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\EventStatus;
use App\Enums\GroupRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Approval;
use App\Models\ApprovalAction;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 承認フロー。
 *
 * 可決条件は「責任者以上の過半数の賛成」。
 * 申請者自身の1票は自動的に賛成として計上し、最高責任者が賛成した時点で即時可決する。
 */
class ApprovalService
{
    public function __construct(
        private readonly EventService $events,
        private readonly NotificationService $notifications,
        private readonly ChangeHistoryService $history,
    ) {}

    /**
     * 承認を申請する。条件を満たしていればその場で可決・適用される。
     */
    public function request(Event $event, User $applicant, ApprovalActionType $actionType): Approval
    {
        $this->assertPreconditions($event, $actionType);

        $existing = $event->approvals()
            ->where('action_type', $actionType->value)
            ->where('status', ApprovalStatus::Pending->value)
            ->first();

        if ($existing !== null) {
            throw new BusinessRuleException('同じ内容の申請がすでに承認待ちです。', 'approval');
        }

        $approval = DB::transaction(function () use ($event, $applicant, $actionType) {
            $approval = Approval::create([
                'group_id' => $event->group_id,
                'event_id' => $event->id,
                'applicant_user_id' => $applicant->id,
                'approvable_type' => Event::class,
                'approvable_id' => $event->id,
                'action_type' => $actionType,
                'status' => ApprovalStatus::Pending,
                'submitted_at' => now(),
            ]);

            // 申請者の1票は自動的に賛成
            $approval->actions()->create([
                'actor_user_id' => $applicant->id,
                'action' => ApprovalAction::APPROVE,
                'acted_at' => now(),
            ]);

            return $approval;
        });

        $this->history->record(
            $applicant,
            $approval,
            'approval.requested',
            ['action_type' => $actionType->value],
            $event->group,
            $event
        );

        $this->notifyApprovers($approval, $applicant);

        return $this->evaluate($approval->fresh(), $applicant);
    }

    /**
     * 賛成・反対を投票する。
     */
    public function vote(Approval $approval, User $actor, bool $approve): Approval
    {
        if (! $approval->isPending()) {
            throw new BusinessRuleException('この申請はすでに処理されています。', 'approval');
        }

        if (! $this->isApprover($actor, $approval)) {
            throw new BusinessRuleException('責任者以上のみ承認できます。', 'approval');
        }

        if ($approval->hasVoted($actor)) {
            throw new BusinessRuleException('すでに投票しています。', 'approval');
        }

        $approval->actions()->create([
            'actor_user_id' => $actor->id,
            'action' => $approve ? ApprovalAction::APPROVE : ApprovalAction::REJECT,
            'acted_at' => now(),
        ]);

        return $this->evaluate($approval->fresh(), $actor);
    }

    /**
     * 可決・否決の判定を行い、可決なら操作を適用する。
     */
    public function evaluate(Approval $approval, User $lastActor): Approval
    {
        if (! $approval->isPending()) {
            return $approval;
        }

        $total = $this->approverCount($approval);
        $needed = intdiv($total, 2) + 1;

        // 脱退・降格した人の票は数えない（分母だけ減って分子が残ると過半数が壊れる）
        $votes = $approval->actions()
            ->get()
            ->filter(fn (ApprovalAction $action) => $approval->group->roleOf($action->actor_user_id)?->isResponsibleOrAbove() === true);

        $approvals = $votes->where('action', ApprovalAction::APPROVE)->count();
        $rejections = $votes->where('action', ApprovalAction::REJECT)->count();

        $byHighest = fn (string $action) => $votes
            ->where('action', $action)
            ->contains(fn (ApprovalAction $a) => $approval->group->roleOf($a->actor_user_id) === GroupRole::HighestResponsible);

        // 最高責任者が賛成した場合は即時可決、反対した場合は即時否決
        if ($byHighest(ApprovalAction::APPROVE) || $approvals >= $needed) {
            return $this->apply($approval, $lastActor);
        }

        if ($byHighest(ApprovalAction::REJECT) || $rejections >= $needed) {
            $approval->update(['status' => ApprovalStatus::Rejected, 'resolved_at' => now()]);

            $this->notifications->notify(
                [$approval->applicant_user_id],
                'approval.rejected',
                $approval->event,
                ['action_type' => $approval->action_type->value]
            );

            return $approval->fresh();
        }

        return $approval;
    }

    /**
     * 申請を取り下げる。申請者本人と最高責任者ができる。
     *
     * 賛否が割れて過半数に届かないまま止まった申請を解消するための出口。
     */
    public function withdraw(Approval $approval, User $actor): Approval
    {
        if (! $approval->isPending()) {
            throw new BusinessRuleException('この申請はすでに処理されています。', 'approval');
        }

        if (! $this->canWithdraw($actor, $approval)) {
            throw new BusinessRuleException('申請した本人か最高責任者のみ取り下げられます。', 'approval');
        }

        $approval->update(['status' => ApprovalStatus::Withdrawn, 'resolved_at' => now()]);

        $this->history->record(
            $actor,
            $approval,
            'approval.withdrawn',
            ['action_type' => $approval->action_type->value],
            $approval->group,
            $approval->event
        );

        $this->notifications->notify(
            [$approval->applicant_user_id],
            'approval.withdrawn',
            $approval->event,
            ['action_type' => $approval->action_type->value]
        );

        return $approval->fresh();
    }

    public function canWithdraw(User $user, Approval $approval): bool
    {
        if (! $approval->isPending()) {
            return false;
        }

        return $approval->applicant_user_id === $user->id
            || $approval->group->roleOf($user) === GroupRole::HighestResponsible;
    }

    /**
     * 責任者の増減があったときに、承認待ちの申請を判定し直す。
     *
     * 承認者が減って過半数の条件が変わった場合に、
     * 誰も投票しないまま止まってしまうのを防ぐ。
     */
    public function reevaluatePending(\App\Models\Group $group, User $actor): void
    {
        $pending = Approval::query()
            ->where('group_id', $group->id)
            ->where('status', ApprovalStatus::Pending->value)
            ->with(['group', 'event'])
            ->get();

        foreach ($pending as $approval) {
            $this->evaluate($approval, $actor);
        }
    }

    /**
     * 可決された操作を実行する。
     */
    private function apply(Approval $approval, User $actor): Approval
    {
        $event = $approval->event;

        DB::transaction(function () use ($approval, $event, $actor) {
            match ($approval->action_type) {
                ApprovalActionType::FixEvent => $this->events->advance($event, $actor),
                ApprovalActionType::CompleteEvent => $this->events->advance($event, $actor),
                ApprovalActionType::ReopenEvent => $this->events->revert($event),
                ApprovalActionType::UnlockContents => null,
            };

            $approval->update([
                'status' => $approval->action_type === ApprovalActionType::UnlockContents
                    ? ApprovalStatus::Approved
                    : ApprovalStatus::Applied,
                'resolved_at' => now(),
            ]);
        });

        $this->history->record(
            $actor,
            $approval,
            'approval.approved',
            ['action_type' => $approval->action_type->value],
            $approval->group,
            $event
        );

        $this->notifications->notify(
            $event->participants()->pluck('users.id')->all(),
            'approval.approved',
            $event,
            ['action_type' => $approval->action_type->value]
        );

        return $approval->fresh();
    }

    /**
     * 確定後の内容変更が解禁されているか。
     */
    public function contentsUnlocked(Event $event): bool
    {
        return $event->approvals()
            ->where('action_type', ApprovalActionType::UnlockContents->value)
            ->where('status', ApprovalStatus::Approved->value)
            ->exists();
    }

    /**
     * 解禁を終了して再びロックする。
     */
    public function relock(Event $event, User $actor): void
    {
        $event->approvals()
            ->where('action_type', ApprovalActionType::UnlockContents->value)
            ->where('status', ApprovalStatus::Approved->value)
            ->update(['status' => ApprovalStatus::Applied->value, 'resolved_at' => now()]);

        $this->history->record($actor, $event, 'event.relocked', [], $event->group, $event);
    }

    /**
     * 申請できる状態かどうかを検証する。
     */
    private function assertPreconditions(Event $event, ApprovalActionType $actionType): void
    {
        match ($actionType) {
            ApprovalActionType::FixEvent => $this->assertStatus($event, EventStatus::Accepting),
            ApprovalActionType::CompleteEvent => $this->assertStatus($event, EventStatus::Settling),
            ApprovalActionType::ReopenEvent => $this->assertStatus($event, EventStatus::Completed),
            ApprovalActionType::UnlockContents => $this->assertUnlockable($event),
        };

        // 状態遷移そのものが可能かどうかも申請時点で検証する
        if (in_array($actionType, [ApprovalActionType::FixEvent, ApprovalActionType::CompleteEvent], true)) {
            $this->events->assertCanAdvance($event);
        }
    }

    private function assertStatus(Event $event, EventStatus $expected): void
    {
        if ($event->status !== $expected) {
            throw new BusinessRuleException(
                'この操作は「'.$expected->label().'」のイベントにのみ申請できます。',
                'approval'
            );
        }
    }

    private function assertUnlockable(Event $event): void
    {
        if (! in_array($event->status, [EventStatus::Fixed, EventStatus::Ongoing], true)) {
            throw new BusinessRuleException(
                '内容変更の解禁は「確定済」「開催中」のイベントにのみ申請できます。',
                'approval'
            );
        }

        if ($this->contentsUnlocked($event)) {
            throw new BusinessRuleException('すでに内容変更が解禁されています。', 'approval');
        }
    }

    /**
     * 承認できる人数（責任者以上）。
     */
    public function approverCount(Approval $approval): int
    {
        return $approval->group->countActiveWithRole(GroupRole::Responsible)
            + $approval->group->countActiveWithRole(GroupRole::HighestResponsible);
    }

    public function isApprover(User $user, Approval $approval): bool
    {
        $role = $approval->group->roleOf($user);

        return $role !== null && $role->isResponsibleOrAbove();
    }

    private function notifyApprovers(Approval $approval, User $applicant): void
    {
        $approverIds = $approval->group->activeMembers()
            ->whereIn('group_members.role', [GroupRole::Responsible->value, GroupRole::HighestResponsible->value])
            ->pluck('users.id')
            ->reject(fn ($id) => $id === $applicant->id)
            ->all();

        $this->notifications->notify(
            $approverIds,
            'approval.requested',
            $approval->event,
            ['action_type' => $approval->action_type->value, 'applicant' => $applicant->displayName()]
        );
    }
}
