<?php

namespace Tests\Support;

use App\Enums\EventStatus;
use App\Enums\GroupRole;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;

trait CreatesGroups
{
    /**
     * 役割ごとの人数を指定してグループを作る。
     *
     * @param  array<string, int>  $counts  ['highest' => 1, 'responsible' => 1, 'member' => 1]
     * @return array{group: Group, highest: array<int, User>, responsible: array<int, User>, member: array<int, User>}
     */
    protected function makeGroup(array $counts = []): array
    {
        $counts = array_merge(['highest' => 1, 'responsible' => 1, 'member' => 1], $counts);

        $group = Group::factory()->create();

        $roleMap = [
            'highest' => GroupRole::HighestResponsible,
            'responsible' => GroupRole::Responsible,
            'member' => GroupRole::Member,
        ];

        $result = ['group' => $group, 'highest' => [], 'responsible' => [], 'member' => []];

        foreach ($roleMap as $key => $role) {
            for ($i = 0; $i < $counts[$key]; $i++) {
                $user = User::factory()->create();
                $group->members()->attach($user->id, [
                    'role' => $role->value,
                    'joined_at' => now(),
                ]);
                $result[$key][] = $user;
            }
        }

        return $result;
    }

    /**
     * グループにイベントを作る。参加者と状態を指定できる。
     *
     * @param  array<int, User>  $participants
     */
    protected function makeEvent(
        Group $group,
        User $creator,
        EventStatus $status = EventStatus::Preparation,
        array $participants = [],
        int $days = 1
    ): Event {
        $event = Event::factory()->create([
            'group_id' => $group->id,
            'created_by' => $creator->id,
            'status' => $status,
            'fixed_at' => $status->isLocked() ? now() : null,
        ]);

        for ($i = 0; $i < $days; $i++) {
            $event->days()->create([
                'event_date' => now()->addDays(30 + $i)->toDateString(),
                'starts_at' => now()->addDays(30 + $i)->setTime(10, 0),
                'ends_at' => now()->addDays(30 + $i)->setTime(16, 0),
            ]);
        }

        foreach ($participants as $participant) {
            $event->participants()->attach($participant->id, ['joined_at' => now()]);
        }

        return $event->fresh();
    }

    /**
     * イベントにサークルと商品を作る。
     *
     * @param  array<int, array{name:string, price:int}>  $products
     * @return array{circle: \App\Models\EventCircle, products: array<int, \App\Models\EventProduct>}
     */
    protected function makeCatalog(Event $event, string $circleName = '夏空スタジオ', array $products = [['name' => '新刊', 'price' => 1000]]): array
    {
        $catalog = app(\App\Services\CatalogService::class);

        $circle = $catalog->createCircle($event, ['display_name' => $circleName, 'booth' => '東1 ア-01a']);

        $created = [];
        foreach ($products as $product) {
            $created[] = $catalog->createProduct($circle, $product);
        }

        return ['circle' => $circle->fresh(), 'products' => $created];
    }

    /**
     * イベントを「精算中」まで進めた状態を作る。
     *
     * @param  array<int, array{circle:string, product:string, price:int, wishes:array<int,int>, assignee:int, purchased:int|null}>  $plan
     *                                                                                                                                      wishes は 参加者インデックス => 希望数、assignee は担当者の参加者インデックス
     * @param  array<int, User>  $participants
     */
    protected function runEventToSettlement(Event $event, array $participants, array $plan): Event
    {
        $catalog = app(\App\Services\CatalogService::class);
        $purchases = app(\App\Services\PurchaseListService::class);
        $results = app(\App\Services\PurchaseResultService::class);
        $events = app(\App\Services\EventService::class);

        $event->update(['status' => EventStatus::Accepting]);
        $event = $event->fresh();

        $items = [];

        foreach ($plan as $entry) {
            $circle = $catalog->createCircle($event, ['display_name' => $entry['circle']]);
            $product = $catalog->createProduct($circle, ['name' => $entry['product'], 'price' => $entry['price']]);

            foreach ($entry['wishes'] as $index => $quantity) {
                $purchases->savePersonalPurchases($event, $participants[$index], [$product->id => $quantity]);
            }

            $shared = $purchases->syncSharedPurchaseFromWishes($circle->fresh(), $participants[$entry['assignee']]);
            $purchases->assign($shared, $participants[$entry['assignee']], $participants[$entry['assignee']]);

            $items[] = [
                'item' => $shared->items()->where('event_product_id', $product->id)->first(),
                'assignee' => $participants[$entry['assignee']],
                'purchased' => $entry['purchased'] ?? array_sum($entry['wishes']),
                'shortages' => $entry['shortages'] ?? [],
                'excess' => $entry['excess'] ?? null,
            ];
        }

        $events->advance($event->fresh());          // 受付中 → 確定済
        $events->advance($event->fresh());          // 確定済 → 開催中

        foreach ($items as $row) {
            if ($row['item'] === null) {
                continue;
            }

            $shortages = [];
            foreach ($row['shortages'] as $index => $quantity) {
                $shortages[$participants[$index]->id] = $quantity;
            }

            $results->recordForSharedItem(
                $row['item'],
                $row['assignee'],
                $row['purchased'],
                null,
                $shortages,
                $row['excess'] !== null ? $participants[$row['excess']]->id : null
            );
        }

        $events->advance($event->fresh());          // 開催中 → 精算中

        return $event->fresh();
    }

    /**
     * 除名・脱退済みの状態にする。
     */
    protected function markAsLeft(Group $group, User $user): void
    {
        $group->members()->updateExistingPivot($user->id, ['left_at' => now()]);
    }
}
