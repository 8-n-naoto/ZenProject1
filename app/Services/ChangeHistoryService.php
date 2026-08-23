<?php

namespace App\Services;

use App\Models\ChangeHistory;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * 変更履歴の記録。
 */
class ChangeHistoryService
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function record(
        ?User $actor,
        Model $subject,
        string $action,
        array $changes = [],
        ?Group $group = null,
        ?Event $event = null
    ): ChangeHistory {
        return ChangeHistory::create([
            'group_id' => $group?->id ?? $event?->group_id,
            'event_id' => $event?->id,
            'actor_user_id' => $actor?->id,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'changes' => $changes,
            'occurred_at' => now(),
        ]);
    }
}
