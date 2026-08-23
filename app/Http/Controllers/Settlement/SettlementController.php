<?php

namespace App\Http\Controllers\Settlement;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\User;
use App\Policies\SettlementPolicy;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettlementController extends Controller
{
    public function __construct(private readonly SettlementService $settlements) {}

    public function index(Event $event): View
    {
        $policy = app(SettlementPolicy::class);
        abort_unless($policy->view(auth()->user(), $event), 403);

        $event->loadMissing('group.activeMembers');

        $settlements = $event->settlements()
            ->with(['payer', 'payee', 'payments', 'event.group.activeMembers'])
            ->get()
            ->sortBy(fn (Settlement $s) => [$s->isCompleted() ? 1 : 0, -$s->amount])
            ->values();

        $userId = auth()->id();

        return view('settlements.index', [
            'event' => $event,
            'settlements' => $settlements,
            'toPay' => $settlements->where('payer_user_id', $userId)->values(),
            'toReceive' => $settlements->where('payee_user_id', $userId)->values(),
            'summary' => $this->settlements->summary($event),
            'shareText' => $this->settlements->shareText($event),
            'canRegenerate' => $policy->regenerate(auth()->user(), $event),
        ]);
    }

    /**
     * メンバー1人分の収支の内訳（立替と購入の明細）。
     */
    public function breakdown(Event $event, User $user): View
    {
        abort_unless(app(SettlementPolicy::class)->view(auth()->user(), $event), 403);

        return view('settlements.breakdown', [
            'event' => $event,
            'member' => $user,
            'breakdown' => $this->settlements->breakdownFor($event, $user),
        ]);
    }

    /**
     * グループをまたいだ自分の未精算一覧。
     */
    public function mine(): View
    {
        $user = auth()->user();

        return view('settlements.mine', [
            'outstanding' => $this->settlements->outstandingFor($user),
            'user' => $user,
        ]);
    }

    public function show(Settlement $settlement): View
    {
        abort_unless(app(SettlementPolicy::class)->view(auth()->user(), $settlement->event), 403);

        $settlement->load(['payer', 'payee', 'payments.items.purchaseResult.eventProduct', 'payments.confirmedBy']);

        $components = $this->settlements->componentsFor($settlement);

        return view('settlements.show', [
            'settlement' => $settlement,
            'event' => $settlement->event,
            'components' => $components,
            'componentResults' => \App\Models\PurchaseResult::query()
                ->with('eventProduct')
                ->whereIn('id', array_column($components, 'purchase_result_id'))
                ->get()
                ->keyBy('id'),
            'canReport' => app(SettlementPolicy::class)->report(auth()->user(), $settlement),
        ]);
    }

    /**
     * 精算リストを作り直す。
     */
    public function regenerate(Event $event): RedirectResponse
    {
        abort_unless(app(SettlementPolicy::class)->regenerate(auth()->user(), $event), 403);

        try {
            $this->settlements->generate($event);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', '精算リストを作り直しました。');
    }

    /**
     * 支払いを報告する。
     */
    public function report(Settlement $settlement): RedirectResponse
    {
        abort_unless(app(SettlementPolicy::class)->report(auth()->user(), $settlement), 403);

        try {
            $this->settlements->reportPayment($settlement, auth()->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', '支払いを報告しました。相手の受取確認をお待ちください。');
    }

    /**
     * 受取を確認する。
     */
    public function confirm(Payment $payment): RedirectResponse
    {
        abort_unless(app(SettlementPolicy::class)->confirm(auth()->user(), $payment), 403);

        try {
            $this->settlements->confirmPayment($payment, auth()->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', '受取を確認しました。精算が完了しました。');
    }

    /**
     * 受け取っていないとして支払い報告を差し戻す。
     */
    public function reject(Payment $payment): RedirectResponse
    {
        abort_unless(app(SettlementPolicy::class)->confirm(auth()->user(), $payment), 403);

        try {
            $this->settlements->rejectPayment($payment);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', '支払い報告を差し戻しました。');
    }
}
