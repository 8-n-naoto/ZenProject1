<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Event;
use App\Services\EventService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * 開催日を迎えたイベントを自動で「開催中」にする。
 *
 *   php artisan events:advance-scheduled
 *
 * 「精算中」への移行は購入結果の登録が必要なため自動化しない。
 * 代わりに、開催が終わったのに結果が未登録のイベントは担当者へ通知する。
 */
class AdvanceScheduledEventsCommand extends Command
{
    protected $signature = 'events:advance-scheduled {--dry-run : 変更せずに対象だけ表示する}';

    protected $description = '開催日を迎えたイベントを開催中にし、未登録の購入結果を担当者に通知します';

    public function handle(EventService $events, NotificationService $notifications): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $started = Event::query()
            ->where('status', EventStatus::Fixed->value)
            ->where('starts_at', '<=', now())
            ->get();

        foreach ($started as $event) {
            if ($dryRun) {
                $this->line('[dry-run] 開催中にする: '.$event->name);

                continue;
            }

            try {
                $events->advance($event);
                $this->info('開催中にしました: '.$event->name);
            } catch (BusinessRuleException $e) {
                $this->warn($event->name.' を進められませんでした: '.$e->getMessage());
            }
        }

        $this->remindMissingWishes($notifications, $dryRun);
        $this->remindUnrecordedResults($notifications, $dryRun);

        return self::SUCCESS;
    }

    /**
     * 開催が近いのに購入希望を登録していない参加者へ知らせる。
     * 同じ日に何度も送らないよう、当日すでに送っていればスキップする。
     */
    private function remindMissingWishes(NotificationService $notifications, bool $dryRun): int
    {
        $events = Event::query()
            ->where('status', EventStatus::Accepting->value)
            ->whereBetween('starts_at', [now(), now()->addDays(3)])
            ->with('participants')
            ->get();

        $reminded = 0;

        foreach ($events as $event) {
            $withWishes = \App\Models\PersonalPurchase::query()
                ->where('event_id', $event->id)
                ->pluck('user_id')
                ->unique();

            $targets = $event->participants
                ->reject(fn ($participant) => $withWishes->contains($participant->id))
                ->pluck('id');

            if ($targets->isEmpty()) {
                continue;
            }

            $alreadySent = \App\Models\Notification::query()
                ->where('event_id', $event->id)
                ->where('type', 'wish.reminder')
                ->whereDate('notified_at', now()->toDateString())
                ->pluck('user_id')
                ->unique();

            $targets = $targets->reject(fn ($id) => $alreadySent->contains($id));

            if ($targets->isEmpty()) {
                continue;
            }

            if ($dryRun) {
                $this->line('[dry-run] 購入希望リマインド: '.$event->name.' / '.$targets->count().'人');

                continue;
            }

            $notifications->notify($targets->all(), 'wish.reminder', $event, [
                'days' => (int) max(0, now()->diffInDays($event->starts_at, false)),
            ]);

            $reminded += $targets->count();
        }

        if ($reminded > 0) {
            $this->info($reminded.'件の購入希望リマインドを送信しました。');
        }

        return $reminded;
    }

    /**
     * 開催が終わったのに購入結果が未登録のイベントについて、担当者に通知する。
     */
    private function remindUnrecordedResults(NotificationService $notifications, bool $dryRun): int
    {
        $ongoing = Event::query()
            ->where('status', EventStatus::Ongoing->value)
            ->where('ends_at', '<', now())
            ->with(['sharedPurchases.assignees', 'sharedPurchases.items.purchaseResult'])
            ->get();

        $reminded = 0;

        foreach ($ongoing as $event) {
            // 1日に複数回実行しても同じ人に何度も通知しない
            $alreadySent = \App\Models\Notification::query()
                ->where('event_id', $event->id)
                ->where('type', 'result.reminder')
                ->whereDate('notified_at', now()->toDateString())
                ->pluck('user_id')
                ->unique();

            foreach ($event->sharedPurchases as $sharedPurchase) {
                $pending = $sharedPurchase->items->filter(fn ($item) => $item->purchaseResult === null);

                if ($pending->isEmpty()) {
                    continue;
                }

                $assigneeIds = $sharedPurchase->assignees
                    ->filter(fn ($assignee) => $assignee->isConfirmed())
                    ->pluck('user_id')
                    ->reject(fn ($id) => $alreadySent->contains($id))
                    ->values()
                    ->all();

                if ($assigneeIds === []) {
                    continue;
                }

                if ($dryRun) {
                    $this->line('[dry-run] 通知: '.$event->name.' / '.$pending->count().'件未登録');

                    continue;
                }

                $notifications->notify($assigneeIds, 'result.reminder', $event, [
                    'pending' => $pending->count(),
                ]);

                $alreadySent = $alreadySent->merge($assigneeIds)->unique();
                $reminded++;
            }
        }

        if ($reminded > 0) {
            $this->info($reminded.'件の購入結果リマインドを送信しました。');
        }

        return $reminded;
    }
}
