<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventCircle;
use App\Models\User;

/**
 * サークル・商品カタログの権限。
 *
 * - 準備中: 責任者以上のみ登録・編集できる
 * - 受付中: 参加者も登録・編集できる
 * - 確定済以降: 変更は承認フロー経由（Phase 9）。直接の編集は不可
 */
class EventCirclePolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return app(EventPolicy::class)->view($user, $event);
    }

    public function view(User $user, EventCircle $eventCircle): bool
    {
        return $this->viewAny($user, $eventCircle->event);
    }

    public function create(User $user, Event $event): bool
    {
        return $this->canEditCatalog($user, $event);
    }

    public function update(User $user, EventCircle $eventCircle): bool
    {
        return $this->canEditCatalog($user, $eventCircle->event);
    }

    public function delete(User $user, EventCircle $eventCircle): bool
    {
        return $this->canEditCatalog($user, $eventCircle->event);
    }

    /**
     * 確定前のカタログ編集ができるか。
     */
    public function canEditCatalog(User $user, Event $event): bool
    {
        if (! $event->group->isActiveMember($user)) {
            return false;
        }

        $role = $event->group->roleOf($user);
        $isResponsible = $role !== null && $role->isResponsibleOrAbove();

        return match ($event->status) {
            EventStatus::Preparation => $isResponsible,
            EventStatus::Accepting => $isResponsible || $event->isParticipant($user),
            // 確定済・開催中は、承認により内容変更が解禁されている場合のみ責任者が編集できる
            EventStatus::Fixed, EventStatus::Ongoing => $isResponsible
                && app(\App\Services\ApprovalService::class)->contentsUnlocked($event),
            default => false,
        };
    }
}
