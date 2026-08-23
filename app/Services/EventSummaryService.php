<?php

namespace App\Services;

use App\Models\Event;
use App\Models\PersonalPurchase;
use App\Models\Settlement;
use App\Models\SharedPurchase;
use App\Models\User;

/**
 * イベント詳細で「自分の状況」を1枚にまとめる。
 */
class EventSummaryService
{
    /**
     * @return array{wishCount:int, wishAmount:int, assignedCircles:int, pendingResults:int, netAmount:?int, isParticipant:bool}
     */
    public function forUser(Event $event, User $user): array
    {
        $wishes = PersonalPurchase::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->with('eventProduct')
            ->get();

        $assigned = SharedPurchase::query()
            ->where('event_id', $event->id)
            ->where(fn ($query) => $query
                ->whereHas('assignees', fn ($q) => $q->where('user_id', $user->id)->whereNotNull('confirmed_at'))
                ->orWhereHas('items.assignees', fn ($q) => $q->where('user_id', $user->id)))
            ->withCount(['items as pending_items_count' => fn ($query) => $query->whereDoesntHave('purchaseResult')])
            ->get();

        $settlement = Settlement::query()
            ->where('event_id', $event->id)
            ->where(fn ($query) => $query
                ->where('payer_user_id', $user->id)
                ->orWhere('payee_user_id', $user->id))
            ->get();

        $net = null;

        if ($settlement->isNotEmpty()) {
            $net = (int) $settlement->sum(
                fn (Settlement $row) => $row->payee_user_id === $user->id ? $row->amount : -$row->amount
            );
        }

        return [
            'wishCount' => (int) $wishes->sum('planned_quantity'),
            'wishAmount' => (int) $wishes->sum(fn (PersonalPurchase $purchase) => $purchase->plannedAmount()),
            'assignedCircles' => $assigned->count(),
            'pendingResults' => (int) $assigned->sum('pending_items_count'),
            'netAmount' => $net,
            'isParticipant' => $event->isParticipant($user),
        ];
    }
}
