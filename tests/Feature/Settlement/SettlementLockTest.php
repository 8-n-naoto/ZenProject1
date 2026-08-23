<?php

namespace Tests\Feature\Settlement;

use App\Enums\EventStatus;
use App\Exceptions\BusinessRuleException;
use App\Services\EventService;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 受取確認まで済んだ精算がある場合、金額が動かせなくなることを確認する。
 */
class SettlementLockTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private function eventWithConfirmedPayment(): array
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $buyer = $highests[0];
        $a = $members[0];
        $b = $members[1];
        $participants = [$buyer, $a, $b];

        $event = $this->makeEvent($group, $buyer, EventStatus::Preparation, $participants);
        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => '夏空スタジオ', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1, 1 => 1, 2 => 2], 'assignee' => 0],
        ]);

        $settlements = app(SettlementService::class);
        $first = $event->settlements()->firstOrFail();
        $payment = $settlements->reportPayment($first, $first->payer);
        $settlements->confirmPayment($payment, $first->payee);

        return ['event' => $event->fresh(), 'buyer' => $buyer, 'participants' => $participants];
    }

    public function test_settling_event_cannot_be_reverted_once_a_payment_is_confirmed(): void
    {
        ['event' => $event] = $this->eventWithConfirmedPayment();

        $this->expectException(BusinessRuleException::class);
        app(EventService::class)->revert($event);
    }

    public function test_results_cannot_be_edited_after_reverting_is_blocked(): void
    {
        ['event' => $event, 'buyer' => $buyer] = $this->eventWithConfirmedPayment();

        $item = $event->sharedPurchases()->firstOrFail()->items()->firstOrFail();

        // 精算中では訂正できない
        $this->actingAs($buyer)
            ->post(route('results.store', $item), ['purchased_quantity' => 4, 'unit_price' => 9000])
            ->assertForbidden();

        // 状態を直接「開催中」にしても（DB操作など）訂正できない
        $event->update(['status' => EventStatus::Ongoing]);

        $this->actingAs($buyer)
            ->post(route('results.store', $item->fresh()), ['purchased_quantity' => 4, 'unit_price' => 9000])
            ->assertForbidden();
    }

    public function test_reopening_a_completed_event_is_still_allowed(): void
    {
        ['event' => $event] = $this->eventWithConfirmedPayment();

        $settlements = app(SettlementService::class);

        foreach ($event->settlements()->where('status', 'pending')->get() as $settlement) {
            $payment = $settlements->reportPayment($settlement, $settlement->payer);
            $settlements->confirmPayment($payment, $settlement->payee);
        }

        $events = app(EventService::class);
        $events->advance($event->fresh());
        $this->assertSame(EventStatus::Completed, $event->fresh()->status);

        // 完了 → 精算中 の再オープンは認める
        $this->assertSame(EventStatus::Settling, $events->revert($event->fresh()));
    }

    public function test_failed_settlement_generation_leaves_the_status_unchanged(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = [$highests[0], $members[0], $members[1]];

        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation, $participants);
        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => '夏空スタジオ', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1, 1 => 1, 2 => 2], 'assignee' => 0],
        ]);

        $settlements = app(SettlementService::class);
        $first = $event->settlements()->firstOrFail();
        $payment = $settlements->reportPayment($first, $first->payer);
        $settlements->confirmPayment($payment, $first->payee);

        // 精算済みがあるので「完了」にはできるが、開催中には戻せない
        $event->update(['status' => EventStatus::Ongoing]);

        try {
            app(EventService::class)->advance($event->fresh());
            $this->fail('精算リストを作り直せないのに進めてしまいました。');
        } catch (BusinessRuleException $e) {
            $this->assertSame(EventStatus::Ongoing, $event->fresh()->status, '失敗したのに状態だけ進んでいます');
        }
    }
}
