<?php

namespace App\Http\Controllers\Event;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Policies\PurchasePolicy;
use App\Services\BudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgets) {}

    /**
     * 自分の予算を設定する。
     */
    public function update(Request $request, Event $event): RedirectResponse
    {
        abort_unless(app(PurchasePolicy::class)->view(auth()->user(), $event), 403);

        $validated = $request->validate([
            'budget' => ['nullable', 'integer', 'min:0', 'max:'.BudgetService::MAX_BUDGET],
        ], [], ['budget' => '予算']);

        try {
            $this->budgets->set($event, $request->user(), $validated['budget'] ?? null);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with(
            'status',
            ($validated['budget'] ?? null) === null ? '予算の設定を解除しました。' : '予算を設定しました。'
        );
    }
}
