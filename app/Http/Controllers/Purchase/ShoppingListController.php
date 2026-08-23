<?php

namespace App\Http\Controllers\Purchase;

use App\Enums\EventStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PersonalPurchase;
use App\Models\SharedPurchase;
use App\Models\SharedPurchaseItem;
use App\Policies\PurchasePolicy;
use App\Services\PurchaseResultService;
use App\Services\SettlementService;
use App\Services\ShoppingListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * イベント当日に使う買い物リスト。
 */
class ShoppingListController extends Controller
{
    public function __construct(
        private readonly ShoppingListService $shopping,
        private readonly PurchaseResultService $results,
        private readonly SettlementService $settlements,
    ) {}

    public function index(Event $event): View
    {
        $policy = app(PurchasePolicy::class);
        abort_unless($policy->view(auth()->user(), $event), 403);

        $list = $this->shopping->forUser($event, auth()->user());

        return view('shopping.index', [
            'event' => $event,
            'circles' => $list['circles'],
            'personal' => $list['personal'],
            'progress' => $list['progress'],
            'canRecord' => $policy->recordResults(auth()->user(), $event),
            'budget' => app(\App\Services\BudgetService::class)->statusFor($event, auth()->user()),
            'isCustomRoute' => app(\App\Services\ShoppingRouteService::class)->savedOrder($event, auth()->user()) !== [],
            'routeShareText' => app(\App\Services\ShoppingRouteService::class)
                ->shareText($event, auth()->user(), $list['circles']),
        ]);
    }

    /**
     * 巡回順を保存する（手動での並べ替え）。
     */
    public function saveRoute(Request $request, Event $event): RedirectResponse
    {
        abort_unless(app(PurchasePolicy::class)->view(auth()->user(), $event), 403);

        $validated = $request->validate([
            'circles' => ['required', 'array', 'max:500'],
            'circles.*' => ['integer'],
        ], [], ['circles' => '巡回順']);

        try {
            app(\App\Services\ShoppingRouteService::class)
                ->save($event, $request->user(), $validated['circles']);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', '巡回順を保存しました。');
    }

    /**
     * 巡回順を既定（完売しやすい順 → 配置順）に戻す。
     */
    public function resetRoute(Request $request, Event $event): RedirectResponse
    {
        abort_unless(app(PurchasePolicy::class)->view(auth()->user(), $event), 403);

        app(\App\Services\ShoppingRouteService::class)->reset($event, $request->user());

        return back()->with('status', '巡回順をおすすめの順に戻しました。');
    }

    /**
     * 明細を「予定どおり買えた」として記録する。
     */
    public function markAsPlanned(SharedPurchaseItem $item): RedirectResponse
    {
        $this->authorizeItem($item);
        $item->loadMissing(['eventProduct', 'sharedPurchase.event']);

        try {
            $this->shopping->recordAsPlanned($item, auth()->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        $this->regenerateIfSettling($item->sharedPurchase->event);

        return back()->with('status', '「'.$item->eventProduct->name.'」を予定どおり購入として記録しました。');
    }

    /**
     * 明細を「買えなかった」として記録する。
     */
    public function markAsSoldOut(SharedPurchaseItem $item): RedirectResponse
    {
        $this->authorizeItem($item);
        $item->loadMissing(['eventProduct', 'sharedPurchase.event']);

        try {
            $this->shopping->recordAsSoldOut($item, auth()->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        $this->regenerateIfSettling($item->sharedPurchase->event);

        return back()->with('status', '「'.$item->eventProduct->name.'」を買えなかったとして記録しました。');
    }

    /**
     * サークルの全明細を「予定どおり買えた」として記録する。
     */
    public function markCircleAsPlanned(SharedPurchase $sharedPurchase): RedirectResponse
    {
        abort_unless(
            app(PurchasePolicy::class)->manageAssignees(auth()->user(), $sharedPurchase)
            || $sharedPurchase->assignees()
                ->where('user_id', auth()->id())
                ->whereNotNull('confirmed_at')
                ->exists(),
            403
        );
        abort_unless(app(PurchasePolicy::class)->recordResults(auth()->user(), $sharedPurchase->event), 403);

        $recorded = 0;

        $sharedPurchase->loadMissing(['items.purchaseResult', 'items.eventProduct', 'event.group']);

        foreach ($sharedPurchase->items as $item) {
            if ($item->purchaseResult !== null) {
                continue;
            }

            try {
                $this->shopping->recordAsPlanned($item, auth()->user());
                $recorded++;
            } catch (BusinessRuleException $e) {
                return back()->withErrors($e->toErrorBag());
            }
        }

        $this->regenerateIfSettling($sharedPurchase->event);

        return back()->with('status', $recorded.'件を予定どおり購入として記録しました。');
    }

    /**
     * 自分で買う分をワンタップで記録する。
     */
    public function markPersonal(PersonalPurchase $purchase, string $outcome): RedirectResponse
    {
        $policy = app(PurchasePolicy::class);
        abort_unless($policy->recordPersonalResult(auth()->user(), $purchase), 403);
        abort_unless(in_array($outcome, ['bought', 'missed'], true), 404);

        $this->results->recordForPersonalPurchase(
            $purchase,
            $outcome === 'bought' ? $purchase->planned_quantity : 0
        );

        $this->regenerateIfSettling($purchase->event);

        return back()->with('status', '購入結果を記録しました。');
    }

    private function authorizeItem(SharedPurchaseItem $item): void
    {
        abort_unless(app(PurchasePolicy::class)->recordSharedResult(auth()->user(), $item), 403);
    }

    private function regenerateIfSettling(Event $event): void
    {
        if ($event->status === EventStatus::Settling) {
            $this->settlements->generate($event);
        }
    }
}
