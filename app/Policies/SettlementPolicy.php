<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\User;

/**
 * 精算・支払いの権限。
 */
class SettlementPolicy
{
    /**
     * 精算一覧の閲覧。イベント参加者と責任者以上。
     */
    public function view(User $user, Event $event): bool
    {
        return $event->group->isActiveMember($user)
            && ($event->isParticipant($user) || $this->isResponsible($user, $event));
    }

    /**
     * 精算リストの再生成。責任者以上・精算中のみ。
     */
    public function regenerate(User $user, Event $event): bool
    {
        return $this->isResponsible($user, $event) && $event->status === EventStatus::Settling;
    }

    /**
     * 支払いを報告できるか。支払う本人のみ。
     */
    public function report(User $user, Settlement $settlement): bool
    {
        $settlement->loadMissing('event.group');

        return $settlement->payer_user_id === $user->id
            && $settlement->event->group->isActiveMember($user)
            && $settlement->event->status === EventStatus::Settling
            && ! $settlement->isCompleted();
    }

    /**
     * 受取を確認できるか。受け取る本人、または責任者以上。
     */
    public function confirm(User $user, Payment $payment): bool
    {
        $payment->loadMissing('event.group');

        if ($payment->event->status !== EventStatus::Settling) {
            return false;
        }

        // 受取確認は受け取る本人だけが行える（完成定義書 2章）
        return $payment->payee_user_id === $user->id
            && $payment->event->group->isActiveMember($user);
    }

    private function isResponsible(User $user, Event $event): bool
    {
        $role = $event->group->roleOf($user);

        return $role !== null && $role->isResponsibleOrAbove();
    }
}
