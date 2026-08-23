<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * アプリ内通知の作成。メール送信は行わない（完成定義書の決定による）。
 */
class NotificationService
{
    /**
     * 指定したユーザーに通知を作成する。
     *
     * @param  array<int, int|User>  $users
     * @param  array<string, mixed>  $payload
     */
    public function notify(array $users, string $type, ?Event $event = null, array $payload = []): void
    {
        $userIds = array_values(array_unique(array_map(
            fn ($user) => $user instanceof User ? $user->id : (int) $user,
            $users
        )));

        if ($userIds === []) {
            return;
        }

        if ($event !== null) {
            $payload = array_merge(['event' => $event->name], $payload);
        }

        $now = now();

        DB::table('notifications')->insert(array_map(fn (int $userId) => [
            'user_id' => $userId,
            'event_id' => $event?->id,
            'type' => $type,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'notified_at' => $now,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $userIds));
    }

    /**
     * イベントの参加者全員に通知する。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, int>  $exceptUserIds
     */
    public function notifyParticipants(Event $event, string $type, array $payload = [], array $exceptUserIds = []): void
    {
        $userIds = $event->participants()
            ->pluck('users.id')
            ->reject(fn ($id) => in_array($id, $exceptUserIds, true))
            ->all();

        $this->notify($userIds, $type, $event, $payload);
    }

    public function unreadCount(User $user): int
    {
        return Notification::query()->where('user_id', $user->id)->unread()->count();
    }

    public function markAllAsRead(User $user): void
    {
        Notification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);
    }
}
