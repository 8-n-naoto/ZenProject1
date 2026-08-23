<?php

namespace App\Http\Controllers\Purchase;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PersonalPurchase;
use App\Models\SharedPurchaseItem;
use App\Policies\PurchasePolicy;
use App\Services\ChangeHistoryService;
use App\Services\PurchaseResultService;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseResultController extends Controller
{
    public function __construct(
        private readonly PurchaseResultService $results,
        private readonly ChangeHistoryService $history,
        private readonly SettlementService $settlements,
    ) {}

    /**
     * 購入結果の入力一覧。自分が担当するサークルを優先して表示する。
     */
    public function index(Event $event): View
    {
        $policy = app(PurchasePolicy::class);
        abort_unless($policy->view(auth()->user(), $event), 403);

        $event->load([
            'sharedPurchases.eventCircle',
            'sharedPurchases.assignees',
            'sharedPurchases.items.eventProduct',
            'sharedPurchases.items.purchaseResult',
        ]);

        $userId = auth()->id();

        $mine = $event->sharedPurchases->filter(
            fn ($sp) => $sp->assignees->contains(fn ($a) => $a->user_id === $userId && $a->isConfirmed())
        )->values();

        $others = $event->sharedPurchases->reject(
            fn ($sp) => $mine->contains('id', $sp->id)
        )->values();

        $personal = PersonalPurchase::query()
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->with(['eventProduct.eventCircle', 'purchaseResult'])
            ->get()
            ->filter(fn (PersonalPurchase $p) => ! $this->coveredBySharedPurchase($p))
            ->values();

        return view('results.index', [
            'event' => $event,
            'mine' => $mine,
            'others' => $others,
            'personal' => $personal,
            'canRecord' => $policy->recordResults(auth()->user(), $event),
        ]);
    }

    /**
     * 共同購入明細の購入結果入力フォーム。
     */
    public function edit(Request $request, SharedPurchaseItem $item): View
    {
        abort_unless(app(PurchasePolicy::class)->recordSharedResult(auth()->user(), $item), 403);

        $item->load(['eventProduct', 'sharedPurchase.eventCircle', 'purchaseResult.shortageUsers', 'purchaseResult.excessTakeover']);

        $demand = $this->results->demandFor($item);
        $result = $item->purchaseResult;

        return view('results.edit', [
            'item' => $item,
            'event' => $item->sharedPurchase->event,
            'fromShoppingList' => $request->query('from') === 'shopping',
            'demand' => $demand,
            'totalDemand' => (int) $demand->sum('planned_quantity'),
            'result' => $result,
            'existingShortages' => $result?->shortageUsers->pluck('shortage_quantity', 'user_id')->all() ?? [],
        ]);
    }

    /**
     * 共同購入明細の購入結果を保存する。
     */
    public function store(Request $request, SharedPurchaseItem $item): RedirectResponse
    {
        abort_unless(app(PurchasePolicy::class)->recordSharedResult(auth()->user(), $item), 403);

        $validated = $request->validate([
            'purchased_quantity' => ['required', 'integer', 'min:0', 'max:999'],
            'unit_price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'shortages' => ['array', 'max:200'],
            'shortages.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'excess_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ], [], [
            'purchased_quantity' => '購入できた数',
            'unit_price' => '実際の単価',
        ]);

        try {
            $this->results->recordForSharedItem(
                $item,
                auth()->user(),
                (int) $validated['purchased_quantity'],
                $validated['unit_price'] ?? null,
                $validated['shortages'] ?? [],
                $validated['excess_user_id'] ?? null,
            );
        } catch (BusinessRuleException $e) {
            return back()->withInput()->withErrors($e->toErrorBag());
        }

        $event = $item->sharedPurchase->event;

        // 精算中に結果を修正した場合は、金額がずれないよう精算リストを作り直す
        $regenerated = $this->regenerateIfSettling($event);

        $this->history->record(auth()->user(), $item, 'result.recorded', [
            'product' => $item->eventProduct->name,
            'purchased' => (int) $validated['purchased_quantity'],
        ], $event->group, $event);

        $status = $regenerated
            ? '購入結果を登録し、精算リストを作り直しました。'
            : '購入結果を登録しました。';

        // 買い物リストから来た場合は、続きを登録できるよう買い物リストに戻す
        if ($request->input('from') === 'shopping') {
            return redirect()->route('shopping.index', $event)->with('status', $status);
        }

        return redirect()->route('results.index', $event)->with('status', $status);
    }

    /**
     * 個人購入（自分で買う分）の結果をまとめて登録する。
     */
    public function storePersonal(Request $request, Event $event): RedirectResponse
    {
        $policy = app(PurchasePolicy::class);
        abort_unless($policy->recordResults(auth()->user(), $event), 403);

        $validated = $request->validate([
            'purchased' => ['array', 'max:2000'],
            'purchased.*' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], ['purchased.*' => '購入できた数']);

        foreach ($validated['purchased'] ?? [] as $purchaseId => $quantity) {
            $purchase = PersonalPurchase::query()
                ->where('event_id', $event->id)
                ->whereKey($purchaseId)
                ->first();

            if ($purchase === null || ! $policy->recordPersonalResult(auth()->user(), $purchase)) {
                continue;
            }

            $this->results->recordForPersonalPurchase($purchase, (int) $quantity);
        }

        $regenerated = $this->regenerateIfSettling($event);

        return redirect()
            ->route('results.index', $event)
            ->with('status', $regenerated
                ? '購入結果を登録し、精算リストを作り直しました。'
                : '購入結果を登録しました。');
    }

    /**
     * 精算中なら精算リストを作り直す。
     */
    private function regenerateIfSettling(Event $event): bool
    {
        if ($event->status !== \App\Enums\EventStatus::Settling) {
            return false;
        }

        $this->settlements->generate($event);

        return true;
    }

    /**
     * その希望が共同購入リストに含まれているか。
     */
    private function coveredBySharedPurchase(PersonalPurchase $purchase): bool
    {
        return SharedPurchaseItem::query()
            ->where('event_product_id', $purchase->event_product_id)
            ->whereHas('sharedPurchase', fn ($query) => $query->where('event_id', $purchase->event_id))
            ->exists();
    }
}
