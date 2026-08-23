<?php

namespace Tests\Feature\Settlement;

use App\Enums\EventStatus;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\Settlement;
use App\Models\SharedPurchaseItem;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 精算金額の整合性に関する回帰テスト。
 */
class SettlementIntegrityTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_minimal_transfers_uses_exact_matches_first(): void
    {
        $service = app(SettlementService::class);

        // +6/+4/+2 と -5/-4/-3 → 4回で済む（-4 と +4 を直接相殺）
        $transfers = $service->minimalTransfers([1 => 6, 2 => 4, 3 => 2, 4 => -5, 5 => -4, 6 => -3]);

        $this->assertCount(4, $transfers);
        $this->assertSame(12, array_sum(array_column($transfers, 'amount')));
    }

    public function test_payment_item_quantities_never_exceed_the_debt(): void
    {
        // A が2つの立替者に債務を持ち、送金が2件に分割されるケース
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = [$responsibles[0], $members[0], $members[1]];
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Preparation, $participants);

        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => 'X', 'product' => 'P1', 'price' => 1000, 'wishes' => [0 => 2], 'assignee' => 1],
            ['circle' => 'Y', 'product' => 'P2', 'price' => 1500, 'wishes' => [1 => 1], 'assignee' => 2],
        ]);

        $service = app(SettlementService::class);

        // 期待: A(-2000) / B(+2000-1500=+500) / C(+1500) → A→C 1500, A→B 500
        $settlements = Settlement::orderBy('id')->get();
        $this->assertSame(2, $settlements->count());

        $quantities = [];
        $amounts = [];

        foreach ($settlements as $settlement) {
            $components = $service->componentsFor($settlement);
            $this->assertSame($settlement->amount, array_sum(array_column($components, 'amount')));

            foreach ($components as $component) {
                $quantities[$component['purchase_result_id']] = ($quantities[$component['purchase_result_id']] ?? 0) + $component['quantity'];
                $amounts[$component['purchase_result_id']] = ($amounts[$component['purchase_result_id']] ?? 0) + $component['amount'];
            }
        }

        // 同じ購入結果の数量合計が、実際の債務数量（2点）を超えてはならない
        foreach ($quantities as $purchaseResultId => $quantity) {
            $expectedQuantity = intdiv($amounts[$purchaseResultId], 1000);
            $this->assertSame($expectedQuantity, $quantity, '購入結果 '.$purchaseResultId.' の数量が金額と一致しません');
        }
    }

    public function test_payment_items_total_matches_the_payment_amount(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = [$responsibles[0], $members[0], $members[1]];
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Preparation, $participants);

        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => 'X', 'product' => 'P1', 'price' => 1000, 'wishes' => [0 => 2], 'assignee' => 1],
            ['circle' => 'Y', 'product' => 'P2', 'price' => 1500, 'wishes' => [1 => 1], 'assignee' => 2],
        ]);

        foreach (Settlement::with(['payer', 'payee'])->get() as $settlement) {
            $this->actingAs($settlement->payer)->post(route('settlements.report', $settlement))->assertRedirect();
        }

        foreach (Payment::all() as $payment) {
            $this->assertSame(
                $payment->total_amount,
                (int) $payment->items()->sum('amount'),
                '支払い明細の合計が支払い総額と一致しません'
            );
        }

        $this->assertGreaterThan(0, PaymentItem::count());
    }

    public function test_correcting_a_result_during_settlement_regenerates_the_list(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = [$responsibles[0], $members[0], $members[1]];
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Preparation, $participants);

        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => 'X', 'product' => 'P1', 'price' => 1000, 'wishes' => [0 => 1], 'assignee' => 1],
        ]);

        $this->assertSame(1000, (int) Settlement::sum('amount'));

        // 精算中に単価を訂正すると、精算リストが作り直される
        $item = SharedPurchaseItem::first();
        $this->actingAs($participants[1])->post(route('results.store', $item), [
            'purchased_quantity' => 1,
            'unit_price' => 400,
        ])->assertRedirect();

        $this->assertSame(400, (int) Settlement::sum('amount'));
        $this->assertSame(1, Settlement::count());
    }

    public function test_results_cannot_be_corrected_after_a_settlement_is_completed(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = [$responsibles[0], $members[0], $members[1]];
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Preparation, $participants);

        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => 'X', 'product' => 'P1', 'price' => 1000, 'wishes' => [0 => 1], 'assignee' => 1],
        ]);

        $settlement = Settlement::first();
        $this->actingAs($settlement->payer)->post(route('settlements.report', $settlement));
        $this->actingAs($settlement->payee)->post(route('payments.confirm', Payment::first()));

        $item = SharedPurchaseItem::first();
        $this->actingAs($participants[1])
            ->post(route('results.store', $item), ['purchased_quantity' => 1, 'unit_price' => 400])
            ->assertForbidden();

        $this->assertSame(1000, (int) Settlement::sum('amount'));
    }

    public function test_totals_balance_across_many_random_scenarios(): void
    {
        $service = app(SettlementService::class);

        $cases = [
            [1 => -1000, 2 => 1000],
            [1 => -3000, 2 => -1000, 3 => 2500, 4 => 1500],
            [1 => -777, 2 => -333, 3 => 1110],
            [1 => -500, 2 => -1500, 3 => 1000, 4 => 1000],
            [1 => -1, 2 => -1, 3 => 1, 4 => 1],
        ];

        foreach ($cases as $balances) {
            $transfers = $service->minimalTransfers($balances);
            $net = [];

            foreach ($transfers as $transfer) {
                $this->assertGreaterThan(0, $transfer['amount']);
                $net[$transfer['payer_id']] = ($net[$transfer['payer_id']] ?? 0) - $transfer['amount'];
                $net[$transfer['payee_id']] = ($net[$transfer['payee_id']] ?? 0) + $transfer['amount'];
            }

            foreach ($balances as $userId => $balance) {
                $this->assertSame($balance, $net[$userId] ?? 0, 'user '.$userId);
            }
        }
    }
}
