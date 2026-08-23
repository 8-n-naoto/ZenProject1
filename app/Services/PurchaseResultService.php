<?php

namespace App\Services;

use App\Enums\PurchaseResultStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\PersonalPurchase;
use App\Models\PurchaseResult;
use App\Models\SharedPurchaseItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 購入結果（実際に買えた数量）の登録と、不足・超過の割り当て。
 */
class PurchaseResultService
{
    /**
     * 共同購入の明細に対する購入結果を登録する。
     *
     * @param  array<int, int>  $shortages  user_id => 受け取れなかった数量
     * @param  array{user_id:?int}|null  $excess  超過分の引取者
     */
    public function recordForSharedItem(
        SharedPurchaseItem $item,
        User $assignee,
        int $purchasedQuantity,
        ?int $unitPrice,
        array $shortages = [],
        ?int $excessUserId = null
    ): PurchaseResult {
        if ($purchasedQuantity < 0) {
            throw new BusinessRuleException('購入数は0以上で入力してください。', 'purchased_quantity');
        }

        $demand = $this->demandFor($item);
        $totalDemand = (int) $demand->sum('planned_quantity');
        $planned = $item->planned_quantity;

        $shortageTotal = max(0, $totalDemand - $purchasedQuantity);
        $excessTotal = max(0, $purchasedQuantity - $totalDemand);

        $this->assertShortageAllocation($demand, $shortages, $shortageTotal);
        $this->assertExcessTakeover($item, $excessUserId);

        if ($excessTotal > 0 && $excessUserId === null) {
            throw new BusinessRuleException(
                '予定より多く購入されています。超過分（'.$excessTotal.'点）を引き取る人を選んでください。',
                'excess_user_id'
            );
        }

        // 立替えた人（返金の受取先）は、実際に買った担当者から変えてはいけない。
        // 責任者が代理で結果を訂正しても、記録済みの立替者をそのまま引き継ぐ。
        $payer = $this->resolvePayer($item, $assignee);

        return DB::transaction(function () use (
            $item, $payer, $purchasedQuantity, $unitPrice, $planned,
            $shortages, $shortageTotal, $excessTotal, $excessUserId
        ) {
            $result = PurchaseResult::updateOrCreate(
                ['shared_purchase_item_id' => $item->id],
                [
                    'personal_purchase_id' => null,
                    'event_product_id' => $item->event_product_id,
                    'purchase_assignee_user_id' => $payer->id,
                    'planned_quantity' => $planned,
                    'purchased_quantity' => $purchasedQuantity,
                    'unit_price' => $unitPrice,
                    'status' => PurchaseResultStatus::fromQuantities($planned, $purchasedQuantity),
                ]
            );

            $result->shortageUsers()->delete();

            if ($shortageTotal > 0) {
                foreach ($shortages as $userId => $quantity) {
                    $quantity = (int) $quantity;

                    if ($quantity <= 0) {
                        continue;
                    }

                    $result->shortageUsers()->create([
                        'user_id' => $userId,
                        'shortage_quantity' => $quantity,
                    ]);
                }
            }

            $result->excessTakeover()?->delete();

            if ($excessTotal > 0 && $excessUserId !== null) {
                $result->excessTakeover()->create([
                    'user_id' => $excessUserId,
                    'takeover_quantity' => $excessTotal,
                ]);
            }

            return $result->fresh(['shortageUsers', 'excessTakeover']);
        });
    }

    /**
     * 個人購入（自分で買う分）の購入結果を登録する。
     */
    public function recordForPersonalPurchase(
        PersonalPurchase $purchase,
        int $purchasedQuantity,
        ?int $unitPrice = null
    ): PurchaseResult {
        if ($purchasedQuantity < 0) {
            throw new BusinessRuleException('購入数は0以上で入力してください。', 'purchased_quantity');
        }

        return PurchaseResult::updateOrCreate(
            ['personal_purchase_id' => $purchase->id],
            [
                'shared_purchase_item_id' => null,
                'event_product_id' => $purchase->event_product_id,
                'purchase_assignee_user_id' => $purchase->user_id,
                'planned_quantity' => $purchase->planned_quantity,
                'purchased_quantity' => $purchasedQuantity,
                'unit_price' => $unitPrice,
                'status' => PurchaseResultStatus::fromQuantities($purchase->planned_quantity, $purchasedQuantity),
            ]
        );
    }

    /**
     * 明細に対する参加者ごとの希望数量。
     *
     * @return Collection<int, PersonalPurchase>
     */
    public function demandFor(SharedPurchaseItem $item): Collection
    {
        $item->loadMissing('sharedPurchase');

        return PersonalPurchase::query()
            ->where('event_id', $item->sharedPurchase->event_id)
            ->where('event_product_id', $item->event_product_id)
            ->with('user')
            ->orderBy('id')
            ->get();
    }

    /**
     * 不足数を希望の多い順に自動割り当てする（提案値）。
     *
     * @return array<int, int> user_id => 不足数
     */
    public function suggestShortageAllocation(SharedPurchaseItem $item, int $purchasedQuantity): array
    {
        $demand = $this->demandFor($item);
        $totalDemand = (int) $demand->sum('planned_quantity');
        $shortage = max(0, $totalDemand - $purchasedQuantity);

        if ($shortage === 0) {
            return [];
        }

        // 希望数の多い人から順に1点ずつ削っていく（公平に分配する）
        $remaining = $demand->mapWithKeys(
            fn (PersonalPurchase $p) => [$p->user_id => $p->planned_quantity]
        )->all();

        $allocation = [];

        while ($shortage > 0 && array_sum($remaining) > 0) {
            arsort($remaining);
            $userId = array_key_first($remaining);

            $remaining[$userId]--;
            $allocation[$userId] = ($allocation[$userId] ?? 0) + 1;
            $shortage--;
        }

        return $allocation;
    }

    /**
     * 超過分の引取者が、そのイベントの参加者かつグループ在籍者であることを検証する。
     */
    /**
     * 立替えた人（精算の受取先）を決める。
     *
     * 1. すでに購入結果があるなら、その立替者を保つ（責任者の訂正で受取先が変わらないように）
     * 2. 記録者が確定済みの購入担当者ならその人
     * 3. どちらでもない（責任者が代理で初回登録した）場合は、確定済みの担当者の先頭
     */
    private function resolvePayer(SharedPurchaseItem $item, User $actor): User
    {
        $existing = $item->purchaseResult;

        if ($existing?->purchase_assignee_user_id !== null) {
            return $existing->assignee ?? $actor;
        }

        $item->loadMissing('sharedPurchase.assignees.user');
        $confirmed = $item->sharedPurchase->assignees->filter(fn ($a) => $a->isConfirmed());

        if ($confirmed->contains(fn ($a) => $a->user_id === $actor->id)) {
            return $actor;
        }

        return $confirmed->first()?->user ?? $actor;
    }

    private function assertExcessTakeover(SharedPurchaseItem $item, ?int $excessUserId): void
    {
        if ($excessUserId === null) {
            return;
        }

        $event = $item->loadMissing('sharedPurchase.event.group')->sharedPurchase->event;

        if (! $event->isParticipant($excessUserId) || ! $event->group->isActiveMember($excessUserId)) {
            throw new BusinessRuleException(
                '超過分を引き取れるのは、このイベントの参加者だけです。',
                'excess_user_id'
            );
        }
    }

    /**
     * 不足数の割り当てが正しいか検証する。
     *
     * @param  Collection<int, PersonalPurchase>  $demand
     * @param  array<int, int>  $shortages
     */
    private function assertShortageAllocation(Collection $demand, array $shortages, int $shortageTotal): void
    {
        $allocated = 0;

        foreach ($shortages as $userId => $quantity) {
            $quantity = (int) $quantity;

            if ($quantity < 0) {
                throw new BusinessRuleException('不足数は0以上で入力してください。', 'shortages');
            }

            $wish = $demand->firstWhere('user_id', (int) $userId);

            if ($wish === null) {
                throw new BusinessRuleException('購入希望を出していない人には不足を割り当てられません。', 'shortages');
            }

            if ($quantity > $wish->planned_quantity) {
                throw new BusinessRuleException(
                    $wish->user->displayName().' さんの不足数が希望数を超えています。',
                    'shortages'
                );
            }

            $allocated += $quantity;
        }

        if ($allocated !== $shortageTotal) {
            throw new BusinessRuleException(
                '不足分の割り当て合計（'.$allocated.'点）が、不足数（'.$shortageTotal.'点）と一致しません。',
                'shortages'
            );
        }
    }

    /**
     * 参加者ごとの受取数量（不足・超過を反映した最終的な取得数）。
     *
     * @return array<int, int> user_id => 受取数
     */
    public function allocationFor(PurchaseResult $result): array
    {
        if (! $result->isShared()) {
            return [$result->purchase_assignee_user_id => $result->purchased_quantity];
        }

        $item = $result->sharedPurchaseItem;
        $allocation = [];

        foreach ($this->demandFor($item) as $wish) {
            $allocation[$wish->user_id] = $wish->planned_quantity;
        }

        foreach ($result->shortageUsers as $shortage) {
            $allocation[$shortage->user_id] = max(0, ($allocation[$shortage->user_id] ?? 0) - $shortage->shortage_quantity);
        }

        $takeover = $result->excessTakeover;

        if ($takeover !== null) {
            $allocation[$takeover->user_id] = ($allocation[$takeover->user_id] ?? 0) + $takeover->takeover_quantity;
        }

        return array_filter($allocation, fn (int $quantity) => $quantity > 0);
    }
}
