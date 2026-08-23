<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\CirclePurchaseAssignee;
use App\Models\Event;
use App\Models\EventCircle;
use App\Models\PersonalPurchase;
use App\Models\SharedPurchase;
use App\Models\SharedPurchaseItem;
use App\Models\User;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * 個人購入リスト・共同購入リスト・購入担当者の操作。
 */
class PurchaseListService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ChangeHistoryService $history,
    ) {}

    /**
     * 個人購入リストを保存する（数量0は削除）。
     *
     * @param  array<int, int>  $quantities  event_product_id => planned_quantity
     */
    public function savePersonalPurchases(Event $event, User $user, array $quantities): void
    {
        // 送られてきたIDをまとめて引き当てる（1件ずつ問い合わせない）
        $products = $event->eventProducts()
            ->whereIn('id', array_map('intval', array_keys($quantities)))
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($event, $user, $quantities, $products) {
            foreach ($quantities as $eventProductId => $quantity) {
                $quantity = max(0, (int) $quantity);

                $product = $products->get((int) $eventProductId);

                if ($product === null) {
                    continue;
                }

                $existing = PersonalPurchase::query()
                    ->where('event_id', $event->id)
                    ->where('event_product_id', $product->id)
                    ->where('user_id', $user->id)
                    ->first();

                if ($quantity === 0) {
                    if ($existing !== null) {
                        $this->assertNoResult($existing);
                        $existing->delete();
                    }

                    continue;
                }

                if ($existing !== null) {
                    $existing->update(['planned_quantity' => $quantity]);

                    continue;
                }

                PersonalPurchase::create([
                    'event_id' => $event->id,
                    'event_product_id' => $product->id,
                    'user_id' => $user->id,
                    'planned_quantity' => $quantity,
                ]);
            }
        });
    }

    /**
     * 過去イベントの購入希望を取り込む。
     *
     * サークル名・商品名の正規化キーで突き合わせ、まだ希望を入れていない商品にだけ反映する。
     * すでに入力済みの数量は上書きしない。
     *
     * @return array{copied: int, skipped: int, missing: int}
     */
    public function copyWishesFrom(Event $target, Event $source, User $user): array
    {
        if ($source->id === $target->id) {
            throw new BusinessRuleException('同じイベントからは取り込めません。', 'source_event_id');
        }

        if ($source->group_id !== $target->group_id) {
            throw new BusinessRuleException('同じグループのイベントからのみ取り込めます。', 'source_event_id');
        }

        $target->loadMissing('eventCircles.eventProducts');

        $index = [];

        foreach ($target->eventCircles as $circle) {
            foreach ($circle->eventProducts as $product) {
                $index[$this->wishKey($circle->display_name, $product->name)] ??= $product->id;
            }
        }

        $wishes = PersonalPurchase::query()
            ->where('event_id', $source->id)
            ->where('user_id', $user->id)
            ->with('eventProduct.eventCircle')
            ->get();

        $existing = PersonalPurchase::query()
            ->where('event_id', $target->id)
            ->where('user_id', $user->id)
            ->pluck('event_product_id')
            ->all();
        $existing = array_flip($existing);

        $copied = 0;
        $skipped = 0;
        $missing = 0;
        $create = [];

        foreach ($wishes as $wish) {
            $product = $wish->eventProduct;
            $key = $this->wishKey($product?->eventCircle?->display_name, $product?->name);
            $targetProductId = $index[$key] ?? null;

            if ($targetProductId === null) {
                $missing++;

                continue;
            }

            if (isset($existing[$targetProductId]) || isset($create[$targetProductId])) {
                $skipped++;

                continue;
            }

            $create[$targetProductId] = $wish->planned_quantity;
            $copied++;
        }

        if ($create !== []) {
            $rows = [];

            foreach ($create as $productId => $quantity) {
                $rows[] = [
                    'event_id' => $target->id,
                    'event_product_id' => $productId,
                    'user_id' => $user->id,
                    'planned_quantity' => $quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // 二重送信で一意制約に当たっても失敗させない（既存の希望は上書きしない方針のため）
            $inserted = PersonalPurchase::query()->insertOrIgnore($rows);

            if ($inserted < $copied) {
                $skipped += $copied - $inserted;
                $copied = $inserted;
            }
        }

        return ['copied' => $copied, 'skipped' => $skipped, 'missing' => $missing];
    }

    /**
     * 購入希望を取り込める過去イベント（同じグループ・自分の希望が1件以上あるもの）。
     *
     * @return \Illuminate\Support\Collection<int, Event>
     */
    public function copyableSourceEvents(Event $target, User $user): \Illuminate\Support\Collection
    {
        return Event::query()
            ->where('group_id', $target->group_id)
            ->whereKeyNot($target->id)
            ->whereHas('personalPurchases', fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('starts_at')
            ->limit(10)
            ->get();
    }

    private function wishKey(?string $circleName, ?string $productName): string
    {
        return TextNormalizer::key($circleName)."\0".TextNormalizer::key($productName);
    }

    /**
     * サークルの共同購入リストを、参加者の希望から作成・更新する。
     */
    public function syncSharedPurchaseFromWishes(EventCircle $circle, User $actor): ?SharedPurchase
    {
        return DB::transaction(function () use ($circle, $actor) {
            $existing = SharedPurchase::query()
                ->where('event_id', $circle->event_id)
                ->where('event_circle_id', $circle->id)
                ->first();

            $hasWishes = PersonalPurchase::query()
                ->whereIn('event_product_id', $circle->eventProducts->pluck('id'))
                ->exists();

            // 誰も希望していないサークルには共同購入リストを作らない
            if (! $hasWishes) {
                if ($existing !== null && $existing->items()->whereHas('purchaseResult')->doesntExist()) {
                    $existing->items()->each(function (SharedPurchaseItem $item) {
                        $item->assignees()->delete();
                        $item->forceDelete();
                    });
                    $existing->assignees()->delete();
                    $existing->delete();
                }

                return null;
            }

            $sharedPurchase = $existing ?? SharedPurchase::create([
                'event_id' => $circle->event_id,
                'event_circle_id' => $circle->id,
                'created_by' => $actor->id,
            ]);

            foreach ($circle->eventProducts as $product) {
                $wished = (int) PersonalPurchase::query()
                    ->where('event_product_id', $product->id)
                    ->sum('planned_quantity');

                $item = $sharedPurchase->items()->where('event_product_id', $product->id)->first();

                if ($wished === 0) {
                    if ($item !== null && $item->purchaseResult()->doesntExist()) {
                        $item->assignees()->delete();
                        $item->delete();
                    }

                    continue;
                }

                if ($item === null) {
                    $sharedPurchase->items()->create([
                        'event_product_id' => $product->id,
                        'planned_quantity' => $wished,
                    ]);

                    continue;
                }

                $item->update(['planned_quantity' => $wished]);
            }

            return $sharedPurchase->fresh(['items']);
        });
    }

    /**
     * 共同購入の明細を手動で調整する。
     */
    public function updateItemQuantity(SharedPurchaseItem $item, int $quantity): void
    {
        if ($quantity < 0) {
            throw new BusinessRuleException('数量は0以上で入力してください。', 'quantity');
        }

        if ($quantity === 0) {
            if ($item->purchaseResult()->exists()) {
                throw new BusinessRuleException('購入結果が登録されているため削除できません。', 'quantity');
            }

            $item->assignees()->delete();
            $item->delete();

            return;
        }

        $item->update(['planned_quantity' => $quantity]);
    }

    /* --------------------------------------------------------------- */
    /* 購入担当者 */
    /* --------------------------------------------------------------- */

    /**
     * サークルの購入担当に立候補する。
     */
    public function volunteer(SharedPurchase $sharedPurchase, User $user): CirclePurchaseAssignee
    {
        $sharedPurchase->loadMissing(['event.group', 'eventCircle']);

        $existing = $sharedPurchase->assignees()->where('user_id', $user->id)->first();

        if ($existing !== null) {
            throw new BusinessRuleException('すでに立候補しています。', 'assignee');
        }

        return $sharedPurchase->assignees()->create([
            'user_id' => $user->id,
            'assigned_quantity' => 1,
            'confirmed_at' => null,
        ]);
    }

    /**
     * 担当者がいないサークルにまとめて立候補する。
     *
     * @return int 立候補したサークル数
     */
    public function volunteerForUnassigned(Event $event, User $user): int
    {
        $targets = SharedPurchase::query()
            ->where('event_id', $event->id)
            ->whereDoesntHave('assignees')
            ->with(['event.group', 'eventCircle'])
            ->get();

        foreach ($targets as $sharedPurchase) {
            $this->volunteer($sharedPurchase, $user);
        }

        return $targets->count();
    }

    /**
     * 立候補を取り下げる。
     */
    public function withdraw(SharedPurchase $sharedPurchase, User $user): void
    {
        $assignee = $sharedPurchase->assignees()->where('user_id', $user->id)->first();

        if ($assignee === null) {
            throw new BusinessRuleException('立候補していません。', 'assignee');
        }

        if ($assignee->isConfirmed()) {
            throw new BusinessRuleException('確定した担当は責任者しか外せません。責任者に連絡してください。', 'assignee');
        }

        $assignee->delete();
    }

    /**
     * 責任者が担当者を指名する（確定込み）。
     */
    public function assign(SharedPurchase $sharedPurchase, User $user, User $actor): CirclePurchaseAssignee
    {
        $sharedPurchase->loadMissing(['event.group', 'eventCircle']);

        $event = $sharedPurchase->event;

        if (! $event->isParticipant($user) || ! $event->group->isActiveMember($user)) {
            throw new BusinessRuleException('イベントに参加している在籍メンバーのみ担当者にできます。', 'assignee');
        }

        $assignee = $sharedPurchase->assignees()->where('user_id', $user->id)->first();

        if ($assignee !== null) {
            $assignee->update(['confirmed_at' => now(), 'assigned_by' => $actor->id]);
            $this->announceAssignment($sharedPurchase, $user, $actor);

            return $assignee->fresh();
        }

        $created = $sharedPurchase->assignees()->create([
            'user_id' => $user->id,
            'assigned_quantity' => 1,
            'confirmed_at' => now(),
            'assigned_by' => $actor->id,
        ]);

        $this->announceAssignment($sharedPurchase, $user, $actor);

        return $created;
    }

    /**
     * 商品ごとの担当者と数量を洗い替えする（1サークルを複数人で分担する場合）。
     *
     * @param  array<int, int>  $quantities  user_id => 担当数量
     */
    public function syncProductAssignees(SharedPurchaseItem $item, array $quantities, User $actor): void
    {
        $item->loadMissing(['sharedPurchase.event.group', 'eventProduct']);
        $event = $item->sharedPurchase->event;

        $cleaned = [];

        foreach ($quantities as $userId => $quantity) {
            $quantity = max(0, (int) $quantity);

            if ($quantity === 0) {
                continue;
            }

            $userId = (int) $userId;

            if (! $event->isParticipant($userId) || ! $event->group->isActiveMember($userId)) {
                throw new BusinessRuleException('イベントに参加している在籍メンバーのみ担当者にできます。', 'assignees');
            }

            $cleaned[$userId] = $quantity;
        }

        $total = array_sum($cleaned);

        if ($total > $item->planned_quantity) {
            throw new BusinessRuleException(
                '担当数量の合計（'.$total.'点）が購入予定数（'.$item->planned_quantity.'点）を超えています。',
                'assignees'
            );
        }

        DB::transaction(function () use ($item, $cleaned, $actor) {
            $item->assignees()->delete();

            foreach ($cleaned as $userId => $quantity) {
                $item->assignees()->create([
                    'user_id' => $userId,
                    'assigned_quantity' => $quantity,
                ]);
            }

            $this->history->record(
                $actor,
                $item,
                'product_assignee.updated',
                [
                    'product' => $item->eventProduct?->name,
                    'assignees' => count($cleaned),
                ],
                $item->sharedPurchase->event->group,
                $item->sharedPurchase->event
            );

            $this->notifications->notify(
                array_keys($cleaned),
                'product_assignee.assigned',
                $item->sharedPurchase->event,
                ['product' => $item->eventProduct?->name]
            );
        });
    }

    /**
     * 担当確定を本人に知らせ、履歴に残す。
     */
    private function announceAssignment(SharedPurchase $sharedPurchase, User $user, User $actor): void
    {
        $circleName = $sharedPurchase->eventCircle?->display_name ?? '';

        $this->notifications->notify(
            [$user->id],
            'assignee.confirmed',
            $sharedPurchase->event,
            ['circle' => $circleName]
        );

        $this->history->record(
            $actor,
            $sharedPurchase,
            'assignee.assigned',
            ['target' => $user->displayName(), 'circle' => $circleName],
            $sharedPurchase->event->group,
            $sharedPurchase->event
        );
    }

    /**
     * 責任者が担当者を外す。
     */
    public function unassign(SharedPurchase $sharedPurchase, User $user): void
    {
        $assignee = $sharedPurchase->assignees()->where('user_id', $user->id)->first();

        if ($assignee === null) {
            throw new BusinessRuleException('この担当者は登録されていません。', 'assignee');
        }

        $assignee->delete();
    }

    /* --------------------------------------------------------------- */

    private function assertNoResult(PersonalPurchase $purchase): void
    {
        if ($purchase->purchaseResult()->exists()) {
            throw new BusinessRuleException('購入結果が登録されているため変更できません。', 'quantity');
        }
    }

    /**
     * イベント内の全サークルについて、共同購入リストを再集計する。
     */
    public function syncAll(Event $event, User $actor): void
    {
        $event->loadMissing('eventCircles.eventProducts');

        foreach ($event->eventCircles as $circle) {
            $this->syncSharedPurchaseFromWishes($circle, $actor);
        }
    }
}
