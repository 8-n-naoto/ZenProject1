<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\PersonalPurchase;
use App\Models\SharedPurchase;
use App\Models\SharedPurchaseItem;
use App\Models\User;

/**
 * 購入リストと購入担当者の権限。
 *
 * - 購入希望（個人購入リスト）の登録・変更は「受付中」かつイベント参加者のみ。
 * - 共同購入リストの集計・調整は責任者以上（受付中）。
 * - 購入担当者の立候補は参加者（受付中）、指名・確定・解除は責任者以上（開催中まで）。
 */
class PurchasePolicy
{
    /**
     * 購入リストの閲覧。イベントを閲覧できる参加者。
     */
    public function view(User $user, Event $event): bool
    {
        return $event->group->isActiveMember($user)
            && ($event->isParticipant($user) || $this->isResponsible($user, $event));
    }

    /**
     * 自分の購入希望を編集できるか。
     */
    public function updateOwnWishes(User $user, Event $event): bool
    {
        return $event->group->isActiveMember($user)
            && $event->status === EventStatus::Accepting
            && $event->isParticipant($user);
    }

    /**
     * 共同購入リストの集計・数量調整。
     */
    public function manageSharedPurchase(User $user, Event $event): bool
    {
        if (! $this->isResponsible($user, $event)) {
            return false;
        }

        if (in_array($event->status, [EventStatus::Preparation, EventStatus::Accepting], true)) {
            return true;
        }

        // 確定済・開催中は、承認により内容変更が解禁されている場合のみ調整できる
        return in_array($event->status, [EventStatus::Fixed, EventStatus::Ongoing], true)
            && app(\App\Services\ApprovalService::class)->contentsUnlocked($event);
    }

    /**
     * 購入担当に立候補できるか。
     */
    public function volunteer(User $user, SharedPurchase $sharedPurchase): bool
    {
        $event = $sharedPurchase->loadMissing('event.group')->event;

        return $event->group->isActiveMember($user)
            && $event->status === EventStatus::Accepting
            && $event->isParticipant($user)
            && $sharedPurchase->assignees()->where('user_id', $user->id)->doesntExist();
    }

    /**
     * 立候補を取り下げられるか。
     */
    public function withdraw(User $user, SharedPurchase $sharedPurchase): bool
    {
        $event = $sharedPurchase->loadMissing('event.group')->event;

        return $event->group->isActiveMember($user)
            && $event->status === EventStatus::Accepting
            && $sharedPurchase->assignees()
                ->where('user_id', $user->id)
                ->whereNull('confirmed_at')
                ->exists();
    }

    /**
     * 担当者の指名・確定・解除。開催中まで責任者が行える。
     */
    public function manageAssignees(User $user, SharedPurchase $sharedPurchase): bool
    {
        $event = $sharedPurchase->loadMissing('event.group')->event;

        return $this->isResponsible($user, $event)
            && in_array($event->status, [
                EventStatus::Preparation,
                EventStatus::Accepting,
                EventStatus::Fixed,
                EventStatus::Ongoing,
            ], true);
    }

    /**
     * 購入結果を登録できる状態か（確定済〜精算中）。
     */
    public function recordResults(User $user, Event $event): bool
    {
        if (! $event->group->isActiveMember($user)) {
            return false;
        }

        if (! $event->isParticipant($user) && ! $this->isResponsible($user, $event)) {
            return false;
        }

        if (! in_array($event->status, [EventStatus::Fixed, EventStatus::Ongoing, EventStatus::Settling], true)) {
            return false;
        }

        // 受取確認まで済んだ精算がある場合、金額が確定しているため購入結果を変更できない。
        // 「精算中 → 開催中」に差し戻しても抜け道にならないよう、状態は問わない。
        if ($this->hasCompletedSettlement($event)) {
            return false;
        }

        return true;
    }

    /**
     * 完了した精算があるか（購入結果の修正可否の判定に使う）。
     */
    private function hasCompletedSettlement(Event $event): bool
    {
        return $event->settlements()
            ->where('status', \App\Enums\SettlementStatus::Completed->value)
            ->exists();
    }

    /**
     * 共同購入の明細に購入結果を登録できるか。
     * 確定した購入担当者、または責任者以上。
     */
    public function recordSharedResult(User $user, SharedPurchaseItem $item): bool
    {
        $event = $item->loadMissing('sharedPurchase.event.group')->sharedPurchase->event;

        if (! $this->recordResults($user, $event)) {
            return false;
        }

        if ($this->isResponsible($user, $event)) {
            return true;
        }

        if ($item->sharedPurchase->assignees()
            ->where('user_id', $user->id)
            ->whereNotNull('confirmed_at')
            ->exists()) {
            return true;
        }

        // 商品単位で担当を割り当てられている場合も登録できる
        return $item->assignees()->where('user_id', $user->id)->exists();
    }

    /**
     * 商品単位の担当割当を編集できるか。責任者、またはそのサークルの確定担当者。
     */
    public function manageProductAssignees(User $user, SharedPurchaseItem $item): bool
    {
        $item->loadMissing('sharedPurchase.event.group');
        $sharedPurchase = $item->sharedPurchase;

        if ($this->manageAssignees($user, $sharedPurchase)) {
            return true;
        }

        return $sharedPurchase->event->status !== EventStatus::Completed
            && $sharedPurchase->assignees()
                ->where('user_id', $user->id)
                ->whereNotNull('confirmed_at')
                ->exists();
    }

    /**
     * 個人購入の結果を登録できるか。本人のみ。
     */
    public function recordPersonalResult(User $user, PersonalPurchase $purchase): bool
    {
        return $purchase->user_id === $user->id
            && $this->recordResults($user, $purchase->loadMissing('event.group')->event);
    }

    private function isResponsible(User $user, Event $event): bool
    {
        $role = $event->group->roleOf($user);

        return $role !== null && $role->isResponsibleOrAbove();
    }
}
