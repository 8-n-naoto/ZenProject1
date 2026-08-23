<?php

namespace App\Http\Controllers\Purchase;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PersonalPurchase;
use App\Policies\PurchasePolicy;
use App\Services\PurchaseListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonalPurchaseController extends Controller
{
    public function __construct(private readonly PurchaseListService $purchases) {}

    /**
     * 自分の購入希望リスト。
     */
    public function index(Event $event): View
    {
        $this->authorizePolicy('view', $event);

        $event->load(['eventCircles.eventProducts']);

        $mine = PersonalPurchase::query()
            ->where('event_id', $event->id)
            ->where('user_id', auth()->id())
            ->with('eventProduct')
            ->get()
            ->keyBy('event_product_id');

        return view('purchases.personal', [
            'event' => $event,
            'circles' => $event->circlesInBoothOrder(),
            'mine' => $mine,
            'canEdit' => app(PurchasePolicy::class)->updateOwnWishes(auth()->user(), $event),
            'totalAmount' => $mine->sum(fn (PersonalPurchase $purchase) => $purchase->plannedAmount()),
            'sourceEvents' => $this->purchases->copyableSourceEvents($event, auth()->user()),
            'budget' => app(\App\Services\BudgetService::class)->statusFor($event, auth()->user()),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorizePolicy('updateOwnWishes', $event);

        $validated = $request->validate([
            'quantities' => ['array', 'max:2000'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], ['quantities.*' => '数量']);

        try {
            $this->purchases->savePersonalPurchases($event, $request->user(), $validated['quantities'] ?? []);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return redirect()
            ->route('purchases.personal.index', $event)
            ->with('status', '購入希望を保存しました。');
    }

    /**
     * 過去イベントの購入希望を取り込む。
     */
    public function copy(Request $request, Event $event): RedirectResponse
    {
        $this->authorizePolicy('updateOwnWishes', $event);

        $validated = $request->validate([
            'source_event_id' => ['required', 'integer'],
        ], [], ['source_event_id' => '取り込み元イベント']);

        $source = Event::query()
            ->where('group_id', $event->group_id)
            ->whereKey($validated['source_event_id'])
            ->first();

        if ($source === null) {
            return back()->withErrors(['source_event_id' => '取り込み元のイベントが見つかりません。']);
        }

        try {
            $result = $this->purchases->copyWishesFrom($event, $source, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        $message = sprintf('「%s」から %d件 の購入希望を取り込みました。', $source->name, $result['copied']);

        if ($result['skipped'] > 0) {
            $message .= sprintf('（入力済み %d件 はそのままにしました）', $result['skipped']);
        }

        if ($result['missing'] > 0) {
            $message .= sprintf('（今回のカタログにない %d件 は取り込めませんでした）', $result['missing']);
        }

        return redirect()
            ->route('purchases.personal.index', $event)
            ->with('status', $message);
    }

    /**
     * 参加者全員の希望をまとめて見る（責任者向け）。
     */
    public function summary(Event $event): View
    {
        $this->authorizePolicy('view', $event);

        $event->load(['eventCircles.eventProducts', 'participants']);

        $purchases = PersonalPurchase::query()
            ->where('event_id', $event->id)
            ->with(['user', 'eventProduct.eventCircle'])
            ->get();

        return view('purchases.summary', [
            'event' => $event,
            'circles' => $event->circlesInBoothOrder(),
            'byCircle' => $purchases->groupBy(fn (PersonalPurchase $p) => $p->eventProduct->event_circle_id),
            'byUser' => $purchases->groupBy('user_id'),
        ]);
    }

    private function authorizePolicy(string $ability, Event $event): void
    {
        abort_unless(
            app(PurchasePolicy::class)->{$ability}(auth()->user(), $event),
            403
        );
    }
}
