<?php

namespace App\Services;

use App\Models\Event;
use App\Models\PersonalPurchase;
use App\Models\SharedPurchase;
use App\Models\SharedPurchaseItem;
use App\Models\User;
use App\Support\BoothSorter;
use Illuminate\Support\Collection;

/**
 * イベント当日の「買い物リスト」を組み立てる。
 *
 * 自分が購入担当として確定しているサークルを配置順に並べ、
 * 何をいくつ買うか・購入結果を登録済みかを1画面で確認できるようにする。
 */
class ShoppingListService
{
    public function __construct(
        private readonly PurchaseResultService $results,
        private readonly ShoppingRouteService $routes,
    ) {}

    /**
     * @return array{circles: Collection<int, array<string, mixed>>, personal: Collection<int, array<string, mixed>>, progress: array{done:int, total:int}}
     */
    public function forUser(Event $event, User $user): array
    {
        $circles = $this->assignedCircles($event, $user);
        $personal = $this->personalItems($event, $user);

        $done = $circles->sum(fn (array $row) => $row['doneCount'])
            + $personal->sum(fn (array $row) => $row['doneCount']);
        $total = $circles->sum(fn (array $row) => count($row['items']))
            + $personal->sum(fn (array $row) => count($row['items']));

        return [
            'circles' => $circles,
            'personal' => $personal,
            'progress' => ['done' => $done, 'total' => $total],
        ];
    }

    /**
     * 自分が確定担当になっているサークル（配置順）。
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function assignedCircles(Event $event, User $user): Collection
    {
        $sharedPurchases = SharedPurchase::query()
            ->where('event_id', $event->id)
            ->where(fn ($query) => $query
                // サークル単位で担当が確定している
                ->whereHas('assignees', fn ($q) => $q
                    ->where('user_id', $user->id)
                    ->whereNotNull('confirmed_at'))
                // または商品単位で担当が割り当てられている
                ->orWhereHas('items.assignees', fn ($q) => $q->where('user_id', $user->id)))
            ->with([
                'eventCircle',
                'items.eventProduct',
                'items.purchaseResult',
                'items.assignees.user',
                'assignees.user',
            ])
            ->get();

        $sorted = $sharedPurchases
            ->map(function (SharedPurchase $sharedPurchase) use ($user) {
                $isCircleAssignee = $sharedPurchase->assignees->contains(
                    fn ($assignee) => $assignee->user_id === $user->id && $assignee->isConfirmed()
                );

                $targetItems = $sharedPurchase->items->filter(
                    fn (SharedPurchaseItem $item) => $isCircleAssignee
                        || $item->assignees->contains(fn ($assignee) => $assignee->user_id === $user->id)
                );
                $items = $targetItems->map(function (SharedPurchaseItem $item) use ($user, $isCircleAssignee) {
                    $mine = $item->assignees->firstWhere('user_id', $user->id);
                    $splitTotal = (int) $item->assignees->sum('assigned_quantity');
                    $demand = (int) $this->results->demandFor($item)->sum('planned_quantity');

                    return [
                        'item' => $item,
                        'result' => $item->purchaseResult,
                        'demand' => $demand,
                        // 商品単位の担当がある場合は自分の担当数、無ければ全量
                        'myQuantity' => $mine !== null
                            ? $mine->assigned_quantity
                            : ($isCircleAssignee ? max(0, $demand - $splitTotal) : 0),
                        'split' => $item->assignees->isNotEmpty(),
                    ];
                })->values()->all();

                $doneCount = collect($items)->filter(fn (array $row) => $row['result'] !== null)->count();

                return [
                    'sharedPurchase' => $sharedPurchase,
                    'circle' => $sharedPurchase->eventCircle,
                    'items' => $items,
                    'isCircleAssignee' => $isCircleAssignee,
                    'doneCount' => $doneCount,
                    'done' => $items !== [] && $doneCount === count($items),
                    'amount' => collect($items)->sum(
                        fn (array $row) => $row['demand'] * (int) ($row['item']->eventProduct?->price ?? 0)
                    ),
                    'partners' => $sharedPurchase->assignees
                        ->filter(fn ($assignee) => $assignee->isConfirmed() && $assignee->user_id !== $user->id)
                        ->pluck('user')
                        ->filter()
                        ->values(),
                ];
            })
            ->reject(fn (array $row) => $row['items'] === []);

        return $this->routes->apply($sorted, $event, $user);
    }

    /**
     * 共同購入に含まれない、自分で買う分（配置順）。
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function personalItems(Event $event, User $user): Collection
    {
        $sharedProductIds = SharedPurchaseItem::query()
            ->whereHas('sharedPurchase', fn ($query) => $query->where('event_id', $event->id))
            ->pluck('event_product_id');

        $purchases = PersonalPurchase::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereNotIn('event_product_id', $sharedProductIds)
            ->with(['eventProduct.eventCircle', 'purchaseResult'])
            ->get();

        return $purchases
            ->groupBy(fn (PersonalPurchase $purchase) => $purchase->eventProduct?->event_circle_id)
            ->map(function (Collection $group) {
                $items = $group->map(fn (PersonalPurchase $purchase) => [
                    'purchase' => $purchase,
                    'result' => $purchase->purchaseResult,
                ])->values()->all();

                return [
                    'circle' => $group->first()->eventProduct?->eventCircle,
                    'items' => $items,
                    'doneCount' => collect($items)->filter(fn (array $row) => $row['result'] !== null)->count(),
                    'amount' => $group->sum(fn (PersonalPurchase $purchase) => $purchase->plannedAmount()),
                ];
            })
            ->sortBy(fn (array $row) => BoothSorter::key($row['circle']?->booth))
            ->values();
    }

    /**
     * 「予定どおり買えた」をワンタップで記録する。
     */
    public function recordAsPlanned(SharedPurchaseItem $item, User $assignee): void
    {
        $demand = (int) $this->results->demandFor($item)->sum('planned_quantity');

        $this->results->recordForSharedItem($item, $assignee, $demand, null);
    }

    /**
     * 「買えなかった」をワンタップで記録する（希望者全員が不足）。
     */
    public function recordAsSoldOut(SharedPurchaseItem $item, User $assignee): void
    {
        $shortages = $this->results->demandFor($item)
            ->mapWithKeys(fn ($wish) => [$wish->user_id => $wish->planned_quantity])
            ->all();

        $this->results->recordForSharedItem($item, $assignee, 0, null, $shortages);
    }
}
