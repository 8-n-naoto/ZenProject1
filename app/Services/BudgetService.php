<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Event;
use App\Models\PersonalPurchase;
use App\Models\PurchaseResult;
use App\Models\User;

/**
 * 参加者ごとの予算と残高。
 *
 * 「予定」と「実績」を分けて持つ:
 *   - 予定 = 購入希望の合計（受付中に見る値）
 *   - 実績 = 購入結果から計算した自分の負担額（当日以降に見る値）
 * 残高は、実績が出ていればそちらを優先して差し引く。
 */
class BudgetService
{
    /** 予算として設定できる上限（1000万円） */
    public const MAX_BUDGET = 10000000;

    public function __construct(private readonly PurchaseResultService $results) {}

    /**
     * 予算を設定する。null で解除。
     */
    public function set(Event $event, User $user, ?int $budget): void
    {
        if (! $event->isParticipant($user)) {
            throw new BusinessRuleException('このイベントに参加していません。', 'budget');
        }

        if ($budget !== null && ($budget < 0 || $budget > self::MAX_BUDGET)) {
            throw new BusinessRuleException('予算は0円〜'.number_format(self::MAX_BUDGET).'円の範囲で入力してください。', 'budget');
        }

        $event->participants()->updateExistingPivot($user->id, ['budget' => $budget]);
    }

    /**
     * 予算と残高の状況。
     *
     * @return array{budget: ?int, planned: int, spent: int, used: int, remaining: ?int, isOver: bool, basis: string}
     */
    public function statusFor(Event $event, User $user): array
    {
        $budget = $this->budgetOf($event, $user);
        $planned = $this->plannedAmount($event, $user);
        $spent = $this->spentAmount($event, $user);

        // 購入結果が1件でも出ていれば実績で見る（当日以降）
        $useActual = $spent > 0 || $event->status->order() >= EventStatus::Ongoing->order();
        $used = $useActual ? $spent : $planned;

        return [
            'budget' => $budget,
            'planned' => $planned,
            'spent' => $spent,
            'used' => $used,
            'remaining' => $budget === null ? null : $budget - $used,
            'isOver' => $budget !== null && $used > $budget,
            'basis' => $useActual ? 'actual' : 'planned',
        ];
    }

    /**
     * 設定済みの予算。
     */
    public function budgetOf(Event $event, User $user): ?int
    {
        $membership = $event->participants()->where('users.id', $user->id)->first();

        $budget = $membership?->pivot?->budget;

        return $budget === null ? null : (int) $budget;
    }

    /**
     * 購入希望の合計金額（予定）。
     */
    public function plannedAmount(Event $event, User $user): int
    {
        return (int) PersonalPurchase::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->with('eventProduct')
            ->get()
            ->sum(fn (PersonalPurchase $purchase) => $purchase->plannedAmount());
    }

    /**
     * 購入結果に基づく自分の負担額（実績）。
     *
     * 立て替えたかどうかに関係なく、「自分が受け取る分」の金額を数える。
     */
    public function spentAmount(Event $event, User $user): int
    {
        $results = PurchaseResult::query()
            ->where(function ($query) use ($event) {
                $query->whereHas('sharedPurchaseItem.sharedPurchase', fn ($q) => $q->where('event_id', $event->id))
                    ->orWhereHas('personalPurchase', fn ($q) => $q->where('event_id', $event->id));
            })
            ->with(['eventProduct', 'shortageUsers', 'excessTakeover', 'sharedPurchaseItem'])
            ->get();

        $total = 0;

        foreach ($results as $result) {
            $quantity = $this->results->allocationFor($result)[$user->id] ?? 0;

            if ($quantity > 0) {
                $total += (int) $quantity * $result->effectiveUnitPrice();
            }
        }

        return $total;
    }

    /**
     * 参加者全員の予算状況（責任者向け）。
     *
     * @return array<int, array{user: User, budget: ?int, used: int, remaining: ?int, isOver: bool}>
     */
    public function overviewFor(Event $event): array
    {
        $event->loadMissing('participants');

        $rows = [];

        foreach ($event->participants as $participant) {
            $status = $this->statusFor($event, $participant);

            $rows[] = [
                'user' => $participant,
                'budget' => $status['budget'],
                'used' => $status['used'],
                'remaining' => $status['remaining'],
                'isOver' => $status['isOver'],
            ];
        }

        return $rows;
    }
}
