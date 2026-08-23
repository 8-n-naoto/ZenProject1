<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Enums\GroupRole;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;

/**
 * イベントに対する権限判定。
 *
 * 前提:
 * - グループに在籍していないユーザーは一切操作できない。
 * - 「確定済」以降は内容がロックされ、変更は承認フロー経由になる（Phase 9）。
 * - 「完了」後は最高責任者以外は閲覧のみ。
 */
class EventPolicy
{
    /**
     * イベント一覧の閲覧。グループの在籍メンバーであること。
     */
    public function viewAny(User $user, Group $group): bool
    {
        return $group->isActiveMember($user);
    }

    /**
     * 詳細の閲覧。
     * 準備中・受付中はグループメンバーなら誰でも見られる（参加を判断するため）。
     * 確定済以降は参加者と責任者以上のみ。
     */
    public function view(User $user, Event $event): bool
    {
        if (! $event->group->isActiveMember($user)) {
            return false;
        }

        if (! $event->status->isLocked()) {
            return true;
        }

        return $event->isParticipant($user) || $this->isResponsible($user, $event);
    }

    /**
     * イベントの作成。責任者以上。
     */
    public function create(User $user, Group $group): bool
    {
        $role = $group->roleOf($user);

        return $role !== null && $role->isResponsibleOrAbove();
    }

    /**
     * イベント情報の編集。責任者以上かつロック前。
     * ロック後は承認フロー経由でのみ変更できる。
     */
    public function update(User $user, Event $event): bool
    {
        if (! $this->isResponsible($user, $event)) {
            return false;
        }

        if (! $event->status->isLocked()) {
            return true;
        }

        // 確定済・開催中は、承認により内容変更が解禁されている場合のみ編集できる
        return in_array($event->status, [EventStatus::Fixed, EventStatus::Ongoing], true)
            && app(\App\Services\ApprovalService::class)->contentsUnlocked($event);
    }

    /**
     * ロック後の変更（承認フロー経由での適用）。完了後は最高責任者のみ。
     */
    public function updateLocked(User $user, Event $event): bool
    {
        if ($event->status->isCompleted()) {
            return $this->isHighestResponsible($user, $event);
        }

        return $this->isResponsible($user, $event);
    }

    /**
     * イベントの削除。最高責任者かつ準備中のみ。
     */
    public function delete(User $user, Event $event): bool
    {
        return $this->isHighestResponsible($user, $event)
            && $event->status === EventStatus::Preparation;
    }

    /**
     * 自分で参加表明する。受付中のみ。
     */
    public function join(User $user, Event $event): bool
    {
        return $event->group->isActiveMember($user)
            && $event->status === EventStatus::Accepting
            && ! $event->isParticipant($user);
    }

    /**
     * 参加を取りやめる。受付中のみ。
     */
    public function leave(User $user, Event $event): bool
    {
        return $event->group->isActiveMember($user)
            && $event->status === EventStatus::Accepting
            && $event->isParticipant($user);
    }

    /**
     * 責任者による参加者の代理追加・削除。受付中のみ。
     */
    public function manageParticipants(User $user, Event $event): bool
    {
        return $this->isResponsible($user, $event)
            && $event->status === EventStatus::Accepting;
    }

    /**
     * 状態を次に進める。精算中→完了のみ最高責任者。
     */
    public function advance(User $user, Event $event): bool
    {
        if ($event->status->next() === null) {
            return false;
        }

        if ($event->status === EventStatus::Settling) {
            return $this->isHighestResponsible($user, $event);
        }

        return $this->isResponsible($user, $event);
    }

    /**
     * 状態を1つ前に戻す。最高責任者のみ。
     */
    public function revert(User $user, Event $event): bool
    {
        return $this->isHighestResponsible($user, $event)
            && $event->status !== EventStatus::Preparation;
    }

    private function isResponsible(User $user, Event $event): bool
    {
        $role = $event->group->roleOf($user);

        return $role !== null && $role->isResponsibleOrAbove();
    }

    private function isHighestResponsible(User $user, Event $event): bool
    {
        return $event->group->roleOf($user) === GroupRole::HighestResponsible;
    }
}
