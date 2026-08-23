<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\EventStatus;
use App\Enums\GroupRole;
use App\Enums\PaymentStatus;
use App\Enums\SettlementStatus;
use App\Models\Approval;
use App\Models\Event;
use App\Models\PersonalPurchase;
use App\Models\Settlement;
use App\Models\SharedPurchase;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * ログイン中のユーザーが「次にやること」を集める。
 *
 * ダッシュボードで、対応が必要なことだけを一覧できるようにする。
 */
class UserTaskService
{
    /**
     * @return Collection<int, array{key:string, title:string, detail:string, url:string, tone:string}>
     */
    public function pendingFor(User $user): Collection
    {
        $groups = $user->activeGroups()->get();
        $groupIds = $groups->pluck('id');

        $events = Event::query()
            ->whereIn('group_id', $groupIds)
            ->active()
            ->with(['participants', 'group'])
            ->get();

        return collect()
            ->merge($this->invitationTasks($user))
            ->merge($this->participationTasks($user, $events))
            ->merge($this->wishTasks($user, $events))
            ->merge($this->shoppingTasks($user, $events))
            ->merge($this->settlementTasks($user, $events))
            ->merge($this->approvalTasks($user, $events))
            ->merge($this->groupSetupTasks($user, $groups))
            ->values();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function invitationTasks(User $user): array
    {
        $count = $user->pendingReceivedInvitations()->count();

        if ($count === 0) {
            return [];
        }

        return [[
            'key' => 'invitations',
            'title' => 'グループ招待に返答する',
            'detail' => $count.'件の招待が未返答です',
            'url' => route('invitations.index'),
            'tone' => 'amber',
        ]];
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array<string, string>>
     */
    private function participationTasks(User $user, Collection $events): array
    {
        return $events
            ->filter(fn (Event $event) => $event->status === EventStatus::Accepting
                && ! $event->isParticipant($user))
            ->map(fn (Event $event) => [
                'key' => 'join:'.$event->id,
                'title' => '参加するか決める',
                'detail' => $event->name.' が参加者を募集中です',
                'url' => route('events.show', $event),
                'tone' => 'sky',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array<string, string>>
     */
    private function wishTasks(User $user, Collection $events): array
    {
        $accepting = $events->filter(fn (Event $event) => $event->status === EventStatus::Accepting
            && $event->isParticipant($user));

        if ($accepting->isEmpty()) {
            return [];
        }

        $withWishes = PersonalPurchase::query()
            ->where('user_id', $user->id)
            ->whereIn('event_id', $accepting->pluck('id'))
            ->pluck('event_id')
            ->unique();

        return $accepting
            ->reject(fn (Event $event) => $withWishes->contains($event->id))
            ->map(fn (Event $event) => [
                'key' => 'wish:'.$event->id,
                'title' => '買いたいものを登録する',
                'detail' => $event->name.' の購入希望がまだ空です',
                'url' => route('purchases.personal.index', $event),
                'tone' => 'sky',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array<string, string>>
     */
    private function shoppingTasks(User $user, Collection $events): array
    {
        $target = $events->filter(fn (Event $event) => in_array($event->status, [
            EventStatus::Fixed, EventStatus::Ongoing, EventStatus::Settling,
        ], true));

        if ($target->isEmpty()) {
            return [];
        }

        $tasks = [];

        foreach ($target as $event) {
            $pending = SharedPurchase::query()
                ->where('event_id', $event->id)
                ->whereHas('assignees', fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->whereNotNull('confirmed_at'))
                ->withCount(['items as pending_items_count' => fn ($query) => $query->whereDoesntHave('purchaseResult')])
                ->get()
                ->sum('pending_items_count');

            if ($pending === 0) {
                continue;
            }

            $tasks[] = [
                'key' => 'shopping:'.$event->id,
                'title' => '購入結果を登録する',
                'detail' => $event->name.' で未登録の商品が'.$pending.'件あります',
                'url' => route('shopping.index', $event),
                'tone' => 'sky',
            ];
        }

        return $tasks;
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array<string, string>>
     */
    private function settlementTasks(User $user, Collection $events): array
    {
        $settling = $events->filter(fn (Event $event) => $event->status === EventStatus::Settling);

        if ($settling->isEmpty()) {
            return [];
        }

        $settlements = Settlement::query()
            ->whereIn('event_id', $settling->pluck('id'))
            ->where('status', SettlementStatus::Pending->value)
            ->where(fn ($query) => $query
                ->where('payer_user_id', $user->id)
                ->orWhere('payee_user_id', $user->id))
            ->with(['event', 'payments', 'payer', 'payee'])
            ->get();

        $tasks = [];

        foreach ($settlements as $settlement) {
            $reported = $settlement->payments->firstWhere('status', PaymentStatus::Reported);

            if ($settlement->payer_user_id === $user->id && $reported === null) {
                $tasks[] = [
                    'key' => 'pay:'.$settlement->id,
                    'title' => '支払う',
                    'detail' => $settlement->payee?->displayName().' さんへ '.$settlement->amountLabel(),
                    'url' => route('settlements.index', $settlement->event_id),
                    'tone' => 'rose',
                ];
            }

            if ($settlement->payee_user_id === $user->id && $reported !== null) {
                $tasks[] = [
                    'key' => 'confirm:'.$settlement->id,
                    'title' => '受け取りを確認する',
                    'detail' => $settlement->payer?->displayName().' さんから '.$settlement->amountLabel().' の支払い報告',
                    'url' => route('settlements.index', $settlement->event_id),
                    'tone' => 'amber',
                ];
            }
        }

        return $tasks;
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array<string, string>>
     */
    private function approvalTasks(User $user, Collection $events): array
    {
        $approvals = Approval::query()
            ->whereIn('event_id', $events->pluck('id'))
            ->where('status', ApprovalStatus::Pending->value)
            ->with(['event', 'group'])
            ->get();

        return $approvals
            ->filter(function (Approval $approval) use ($user) {
                $role = $approval->group?->roleOf($user);

                return $role !== null
                    && $role->isResponsibleOrAbove()
                    && ! $approval->hasVoted($user);
            })
            ->map(fn (Approval $approval) => [
                'key' => 'approval:'.$approval->id,
                'title' => '承認する',
                'detail' => $approval->event?->name.'「'.$approval->action_type->label().'」の承認待ち',
                'url' => route('approvals.index', $approval->event_id),
                'tone' => 'amber',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, \App\Models\Group>  $groups
     * @return array<int, array<string, string>>
     */
    private function groupSetupTasks(User $user, Collection $groups): array
    {
        return $groups
            ->filter(fn ($group) => $group->roleOf($user) === GroupRole::HighestResponsible
                && $group->countActiveWithRole(GroupRole::Responsible) === 0)
            ->map(fn ($group) => [
                'key' => 'responsible:'.$group->id,
                'title' => '責任者を任命する',
                'detail' => $group->name.' に責任者がいません（イベント作成に必要です）',
                'url' => route('groups.show', $group),
                'tone' => 'amber',
            ])
            ->values()
            ->all();
    }
}
