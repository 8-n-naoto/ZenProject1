<?php

namespace App\Http\Controllers\Purchase;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\SharedPurchase;
use App\Models\SharedPurchaseItem;
use App\Models\User;
use App\Policies\PurchasePolicy;
use App\Services\PurchaseListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SharedPurchaseController extends Controller
{
    public function __construct(private readonly PurchaseListService $purchases) {}

    /**
     * 共同購入リスト（サークル単位）。
     */
    public function index(Event $event): View
    {
        $this->authorizePolicy('view', $event);

        $event->load([
            'eventCircles.eventProducts',
            'sharedPurchases.items.eventProduct',
            'sharedPurchases.assignees.user',
            'sharedPurchases.eventCircle',
        ]);

        return view('purchases.shared', [
            'event' => $event,
            'sharedPurchases' => $event->sharedPurchases->sortBy(
                fn (SharedPurchase $sp) => \App\Support\BoothSorter::key($sp->eventCircle?->booth)
            ),
            'canManage' => app(PurchasePolicy::class)->manageSharedPurchase(auth()->user(), $event),
            'unassignedCount' => $event->sharedPurchases
                ->filter(fn (SharedPurchase $sharedPurchase) => $sharedPurchase->assignees->isEmpty())
                ->count(),
            'canVolunteerAll' => $event->status === \App\Enums\EventStatus::Accepting
                && $event->isParticipant(auth()->user()),
        ]);
    }

    /**
     * 参加者の希望から共同購入リストを再集計する。
     */
    public function sync(Event $event): RedirectResponse
    {
        $this->authorizePolicy('manageSharedPurchase', $event);

        $this->purchases->syncAll($event, auth()->user());

        return back()->with('status', '購入希望から共同購入リストを再集計しました。');
    }

    public function show(SharedPurchase $sharedPurchase): View
    {
        $this->authorizePolicy('view', $sharedPurchase->event);

        $sharedPurchase->load([
            'eventCircle', 'items.eventProduct', 'items.assignees.user', 'assignees.user', 'event.group',
        ]);

        $user = auth()->user();
        $policy = app(PurchasePolicy::class);

        $candidates = $policy->manageAssignees($user, $sharedPurchase)
            ? $sharedPurchase->event->participants()
                ->whereNotIn('users.id', $sharedPurchase->assignees->pluck('user_id'))
                ->get()
            : collect();

        return view('purchases.shared-show', [
            'sharedPurchase' => $sharedPurchase,
            'event' => $sharedPurchase->event,
            'canManage' => $policy->manageSharedPurchase($user, $sharedPurchase->event),
            'canManageAssignees' => $policy->manageAssignees($user, $sharedPurchase),
            'canVolunteer' => $policy->volunteer($user, $sharedPurchase),
            'canWithdraw' => $policy->withdraw($user, $sharedPurchase),
            'canSplit' => $sharedPurchase->items->isNotEmpty()
                && $policy->manageProductAssignees($user, $sharedPurchase->items->first()),
            'participants' => $sharedPurchase->event->participants()->get(),
            'candidates' => $candidates,
        ]);
    }

    /**
     * 商品ごとの担当者と数量を割り当てる。
     */
    public function updateProductAssignees(Request $request, SharedPurchaseItem $item): RedirectResponse
    {
        abort_unless(app(PurchasePolicy::class)->manageProductAssignees(auth()->user(), $item), 403);

        $validated = $request->validate([
            'assignees' => ['array', 'max:200'],
            'assignees.*' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], ['assignees.*' => '担当数量']);

        try {
            $this->purchases->syncProductAssignees($item, $validated['assignees'] ?? [], auth()->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return redirect()
            ->route('purchases.shared.show', $item->sharedPurchase)
            ->with('status', '商品ごとの担当を更新しました。');
    }

    /**
     * 明細の数量を手動調整する。
     */
    public function updateItem(Request $request, SharedPurchaseItem $item): RedirectResponse
    {
        $this->authorizePolicy('manageSharedPurchase', $item->sharedPurchase->event);

        $validated = $request->validate([
            'planned_quantity' => ['required', 'integer', 'min:0', 'max:999'],
        ], [], ['planned_quantity' => '数量']);

        $sharedPurchase = $item->sharedPurchase;

        try {
            $this->purchases->updateItemQuantity($item, (int) $validated['planned_quantity']);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return redirect()
            ->route('purchases.shared.show', $sharedPurchase)
            ->with('status', '数量を更新しました。');
    }

    /* --------------------------------------------------------------- */
    /* 購入担当者 */
    /* --------------------------------------------------------------- */

    /**
     * 担当者がいないサークルにまとめて立候補する。
     */
    public function volunteerForUnassigned(Event $event): RedirectResponse
    {
        $policy = app(PurchasePolicy::class);
        abort_unless($policy->view(auth()->user(), $event), 403);
        abort_unless(
            $event->status === \App\Enums\EventStatus::Accepting && $event->isParticipant(auth()->user()),
            403
        );

        try {
            $count = $this->purchases->volunteerForUnassigned($event, auth()->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', $count === 0
            ? '担当者がいないサークルはありません。'
            : $count.'サークルの購入担当に立候補しました。');
    }

    public function volunteer(SharedPurchase $sharedPurchase): RedirectResponse
    {
        abort_unless(app(PurchasePolicy::class)->volunteer(auth()->user(), $sharedPurchase), 403);

        try {
            $this->purchases->volunteer($sharedPurchase, auth()->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', 'このサークルの購入担当に立候補しました。');
    }

    public function withdraw(SharedPurchase $sharedPurchase): RedirectResponse
    {
        abort_unless(app(PurchasePolicy::class)->withdraw(auth()->user(), $sharedPurchase), 403);

        try {
            $this->purchases->withdraw($sharedPurchase, auth()->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', '立候補を取り下げました。');
    }

    public function assign(SharedPurchase $sharedPurchase, User $user): RedirectResponse
    {
        abort_unless(app(PurchasePolicy::class)->manageAssignees(auth()->user(), $sharedPurchase), 403);

        try {
            $this->purchases->assign($sharedPurchase, $user, auth()->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', $user->user_id.' さんを購入担当に確定しました。');
    }

    public function unassign(SharedPurchase $sharedPurchase, User $user): RedirectResponse
    {
        abort_unless(app(PurchasePolicy::class)->manageAssignees(auth()->user(), $sharedPurchase), 403);

        try {
            $this->purchases->unassign($sharedPurchase, $user);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', $user->user_id.' さんを購入担当から外しました。');
    }

    private function authorizePolicy(string $ability, Event $event): void
    {
        abort_unless(app(PurchasePolicy::class)->{$ability}(auth()->user(), $event), 403);
    }
}
