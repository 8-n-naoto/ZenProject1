<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\GroupRole;
use App\Enums\SettlementStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ChangeHistoryService $history,
    ) {}

    /**
     * イベントを作成する。
     *
     * @param  array{name:string,venue_name:string,venue_address?:?string,description?:?string,days:array<int,array{event_date:string,starts_at?:?string,ends_at?:?string}>}  $data
     */
    public function create(Group $group, User $creator, array $data): Event
    {
        if ($group->countActiveWithRole(GroupRole::Responsible) === 0) {
            throw new BusinessRuleException(
                'イベントを作成するには、グループに責任者が1人以上必要です。先に責任者を任命してください。',
                'event'
            );
        }

        return DB::transaction(function () use ($group, $creator, $data) {
            [$startsAt, $endsAt] = $this->rangeFromDays($data['days']);

            $event = $group->events()->create([
                'created_by' => $creator->id,
                'name' => $data['name'],
                'venue_name' => $data['venue_name'],
                'venue_address' => $data['venue_address'] ?? null,
                'description' => $data['description'] ?? null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => EventStatus::Preparation,
            ]);

            $this->syncDays($event, $data['days']);

            $this->history->record($creator, $event, 'event.created', ['name' => $event->name], $group, $event);

            return $event;
        });
    }

    /**
     * イベント情報を更新する。
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, array $data): Event
    {
        return DB::transaction(function () use ($event, $data) {
            [$startsAt, $endsAt] = $this->rangeFromDays($data['days']);

            $event->update([
                'name' => $data['name'],
                'venue_name' => $data['venue_name'],
                'venue_address' => $data['venue_address'] ?? null,
                'description' => $data['description'] ?? null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            $this->syncDays($event, $data['days']);

            return $event->fresh(['days']);
        });
    }

    /**
     * 既存イベントのサークル・商品を引き継いで、新しいイベントを作る。
     *
     * 購入希望・担当者・購入結果は引き継がない（新しいイベントの実績として登録し直す）。
     *
     * @param  array<string, mixed>  $data
     */
    public function duplicate(Event $source, User $creator, array $data): Event
    {
        $event = $this->create($source->group, $creator, $data);

        $images = app(ImageStorageService::class);

        DB::transaction(function () use ($source, $event, $images, $creator) {
            $source->loadMissing(['eventCircles.circle', 'eventCircles.eventProducts.product']);

            foreach ($source->eventCircles as $sourceCircle) {
                $circle = \App\Models\Circle::create([
                    'name' => $sourceCircle->display_name,
                    'website_url' => $sourceCircle->circle?->website_url,
                    'description' => $sourceCircle->circle?->description,
                ]);

                $newCircle = $event->eventCircles()->create([
                    'circle_id' => $circle->id,
                    'display_name' => $sourceCircle->display_name,
                    'booth' => $sourceCircle->booth,
                    'map_image_path' => $images->duplicate($sourceCircle->map_image_path, 'circles'),
                ]);

                foreach ($sourceCircle->eventProducts as $sourceProduct) {
                    $product = \App\Models\Product::create([
                        'name' => $sourceProduct->name,
                        'description' => $sourceProduct->product?->description,
                    ]);

                    $newCircle->eventProducts()->create([
                        'event_id' => $event->id,
                        'product_id' => $product->id,
                        'name' => $sourceProduct->name,
                        'price' => $sourceProduct->price,
                        'status' => \App\Enums\ProductStatus::Selling->value,
                        'image_path' => $images->duplicate($sourceProduct->image_path, 'products'),
                    ]);
                }
            }

            $this->history->record($creator, $event, 'event.duplicated', [
                'source' => $source->name,
                'circles' => $source->eventCircles->count(),
            ], $event->group, $event);
        });

        return $event->fresh(['days', 'eventCircles']);
    }

    /**
     * 参加表明。
     */
    public function join(Event $event, User $user): void
    {
        if ($event->isParticipant($user)) {
            throw new BusinessRuleException('すでにこのイベントに参加しています。', 'event');
        }

        $event->participants()->attach($user->id, ['joined_at' => now()]);
    }

    /**
     * 参加の取りやめ。
     */
    public function leave(Event $event, User $user): void
    {
        if (! $event->isParticipant($user)) {
            throw new BusinessRuleException('このイベントに参加していません。', 'event');
        }

        $this->assertNoPurchaseData($event, $user);

        $event->participants()->detach($user->id);
    }

    /**
     * 状態をひとつ進める。
     */
    public function advance(Event $event, ?User $actor = null): EventStatus
    {
        $next = $event->status->next();

        if ($next === null) {
            throw new BusinessRuleException('これ以上進められる状態がありません。', 'event');
        }

        $this->assertCanEnter($event, $next);

        $attributes = ['status' => $next];

        if ($next === EventStatus::Fixed && $event->fixed_at === null) {
            $attributes['fixed_at'] = now();
        }

        // 精算リストの生成に失敗したら状態も戻す（中途半端な状態を残さない）
        DB::transaction(function () use ($event, $attributes, $next, $actor) {
            $event->update($attributes);

            if ($next === EventStatus::Settling) {
                $settlements = app(SettlementService::class)->generate($event->fresh());

                $this->notifications->notifyParticipants($event, 'settlement.generated');
                $this->history->record($actor, $event, 'settlement.generated', ['count' => $settlements->count()], $event->group, $event);
            }
        });

        $this->history->record($actor, $event, 'event.status_changed', [
            'from' => $event->getOriginal('status') instanceof EventStatus
                ? $event->getOriginal('status')->label()
                : (EventStatus::tryFrom((string) $event->getOriginal('status'))?->label() ?? ''),
            'to' => $next->label(),
        ], $event->group, $event);

        $this->notifications->notifyParticipants($event, match ($next) {
            EventStatus::Accepting => 'event.accepting',
            EventStatus::Fixed => 'event.fixed',
            EventStatus::Ongoing => 'event.ongoing',
            EventStatus::Settling => 'event.settling',
            EventStatus::Completed => 'event.completed',
            default => 'event.updated',
        });

        return $next;
    }

    /**
     * 状態をひとつ戻す。
     */
    public function revert(Event $event): EventStatus
    {
        $previous = collect(EventStatus::cases())
            ->firstWhere(fn (EventStatus $status) => $status->next() === $event->status);

        if ($previous === null) {
            throw new BusinessRuleException('これ以上戻せる状態がありません。', 'event');
        }

        // 「精算中 → 開催中」に戻すと購入結果を書き換えられてしまうため、
        // 受取確認まで済んだ精算がある場合は戻せない。
        // （「完了 → 精算中」の再オープンは、金額が確定した後の運用として認める）
        if ($event->status === EventStatus::Settling
            && $event->settlements()->where('status', SettlementStatus::Completed->value)->exists()) {
            throw new BusinessRuleException(
                '受取確認まで済んだ精算があるため、開催中には戻せません。',
                'event'
            );
        }

        $attributes = ['status' => $previous];

        if ($previous->order() < EventStatus::Fixed->order()) {
            $attributes['fixed_at'] = null;
        }

        $event->update($attributes);

        return $previous;
    }

    /* --------------------------------------------------------------- */

    /**
     * 次の状態に進めるかどうかを検証する（申請時の事前チェックに使う）。
     */
    public function assertCanAdvance(Event $event): void
    {
        $next = $event->status->next();

        if ($next === null) {
            throw new BusinessRuleException('これ以上進められる状態がありません。', 'event');
        }

        $this->assertCanEnter($event, $next);
    }

    /**
     * 次の状態に進むための条件を検証する。
     */
    private function assertCanEnter(Event $event, EventStatus $next): void
    {
        match ($next) {
            EventStatus::Accepting => $this->assertReadyForAccepting($event),
            EventStatus::Fixed => $this->assertReadyForFixing($event),
            EventStatus::Settling => $this->assertReadyForSettling($event),
            EventStatus::Completed => $this->assertReadyForCompletion($event),
            default => null,
        };
    }

    private function assertReadyForAccepting(Event $event): void
    {
        if ($event->days()->count() === 0) {
            throw new BusinessRuleException('開催日が登録されていません。', 'event');
        }

        if ($event->group->countActiveWithRole(GroupRole::Responsible) === 0) {
            throw new BusinessRuleException('グループに責任者が1人以上必要です。', 'event');
        }
    }

    private function assertReadyForFixing(Event $event): void
    {
        if ($event->participants()->count() === 0) {
            throw new BusinessRuleException('参加者が1人もいません。参加者を確定してから進めてください。', 'event');
        }

        // 共同購入リストがある場合は、全てのサークルに確定した購入担当者が必要。
        $withoutAssignee = $event->sharedPurchases()
            ->whereDoesntHave('assignees', fn ($query) => $query->whereNotNull('confirmed_at'))
            ->with('eventCircle')
            ->get();

        if ($withoutAssignee->isNotEmpty()) {
            $names = $withoutAssignee->take(3)->map(fn ($sp) => $sp->eventCircle->display_name)->implode('、');
            $suffix = $withoutAssignee->count() > 3 ? ' ほか'.($withoutAssignee->count() - 3).'件' : '';

            throw new BusinessRuleException(
                '購入担当者が確定していないサークルがあります（'.$names.$suffix.'）。担当者を確定してから進めてください。',
                'event'
            );
        }
    }

    /**
     * 精算に入るには、全ての共同購入明細に購入結果が登録されている必要がある。
     */
    private function assertReadyForSettling(Event $event): void
    {
        $missing = \App\Models\SharedPurchaseItem::query()
            ->whereHas('sharedPurchase', fn ($query) => $query->where('event_id', $event->id))
            ->whereDoesntHave('purchaseResult')
            ->count();

        if ($missing > 0) {
            throw new BusinessRuleException(
                '購入結果が未登録の商品が '.$missing.'件 あります。全て登録してから精算に進んでください。',
                'event'
            );
        }
    }

    /**
     * 全ての精算が完了していなければイベントを完了できない。
     */
    private function assertReadyForCompletion(Event $event): void
    {
        $pending = $event->settlements()
            ->where('status', SettlementStatus::Pending->value)
            ->count();

        if ($pending > 0) {
            throw new BusinessRuleException(
                '未精算の送金が '.$pending.'件 残っています。全て完了してからイベントを完了してください。',
                'event'
            );
        }
    }

    /**
     * 購入希望を登録済みの参加者は抜けられない。
     */
    private function assertNoPurchaseData(Event $event, User $user): void
    {
        $hasPersonal = $event->personalPurchases()->where('user_id', $user->id)->exists();

        if ($hasPersonal) {
            throw new BusinessRuleException(
                '購入希望が登録されています。先に個人購入リストを空にしてください。',
                'event'
            );
        }
    }

    /**
     * 開催日から全体の開始・終了日時を求める。
     *
     * @param  array<int, array<string, mixed>>  $days
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function rangeFromDays(array $days): array
    {
        $starts = [];
        $ends = [];

        foreach ($days as $day) {
            $date = CarbonImmutable::parse($day['event_date']);
            $starts[] = $this->combine($date, $day['starts_at'] ?? null, '00:00');
            $ends[] = $this->combine($date, $day['ends_at'] ?? null, '23:59');
        }

        return [min($starts), max($ends)];
    }

    private function combine(CarbonImmutable $date, ?string $time, string $fallback): CarbonImmutable
    {
        $time = $time !== null && $time !== '' ? $time : $fallback;
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return $date->setTime((int) $hour, (int) $minute);
    }

    /**
     * 開催日を洗い替えする。
     *
     * @param  array<int, array<string, mixed>>  $days
     */
    private function syncDays(Event $event, array $days): void
    {
        $event->days()->delete();

        foreach ($days as $day) {
            $date = CarbonImmutable::parse($day['event_date']);

            $event->days()->create([
                'event_date' => $date->toDateString(),
                'starts_at' => isset($day['starts_at']) && $day['starts_at'] !== ''
                    ? $this->combine($date, $day['starts_at'], '00:00')
                    : null,
                'ends_at' => isset($day['ends_at']) && $day['ends_at'] !== ''
                    ? $this->combine($date, $day['ends_at'], '23:59')
                    : null,
            ]);
        }
    }
}
