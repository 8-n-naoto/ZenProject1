<?php

namespace Tests\Feature\Settlement;

use App\Enums\EventStatus;
use App\Enums\PaymentStatus;
use App\Enums\SettlementStatus;
use App\Models\Payment;
use App\Models\Settlement;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    /**
     * 参加者3人 A(責任者)/B/C。
     *  - サークルX 商品1000円: A=1, B=2, C=1 を B が購入（Bが4000円立替）
     *  - サークルY 商品500円 : A=2, C=2 を C が購入（Cが2000円立替）
     * 債務: A→B 1000 / C→B 1000 / A→C 1000
     * 純額: A -2000 / B +2000 / C ±0  → 送金は A→B 2000 の1件だけ
     */
    private function scenario(): array
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = [$responsibles[0], $members[0], $members[1]];
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Preparation, $participants);

        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => 'サークルX', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1, 1 => 2, 2 => 1], 'assignee' => 1],
            ['circle' => 'サークルY', 'product' => 'グッズ', 'price' => 500, 'wishes' => [0 => 2, 2 => 2], 'assignee' => 2],
        ]);

        return ['group' => $group, 'event' => $event, 'a' => $participants[0], 'b' => $participants[1], 'c' => $participants[2]];
    }

    public function test_settlements_are_generated_when_settlement_starts(): void
    {
        ['event' => $event, 'a' => $a, 'b' => $b] = $this->scenario();

        $this->assertSame(EventStatus::Settling, $event->status);

        $settlements = Settlement::all();
        $this->assertCount(1, $settlements);

        $settlement = $settlements->first();
        $this->assertSame($a->id, $settlement->payer_user_id);
        $this->assertSame($b->id, $settlement->payee_user_id);
        $this->assertSame(2000, $settlement->amount);
        $this->assertSame(SettlementStatus::Pending, $settlement->status);
    }

    public function test_balances_are_correct(): void
    {
        ['event' => $event, 'a' => $a, 'b' => $b, 'c' => $c] = $this->scenario();

        $balances = app(SettlementService::class)->balances($event);

        $this->assertSame(-2000, $balances[$a->id]);
        $this->assertSame(2000, $balances[$b->id]);
        $this->assertArrayNotHasKey($c->id, $balances);
    }

    public function test_summary_shows_spent_and_owed(): void
    {
        ['event' => $event, 'a' => $a, 'b' => $b, 'c' => $c] = $this->scenario();

        $summary = collect(app(SettlementService::class)->summary($event))->keyBy(fn ($row) => $row['user']->id);

        $this->assertSame(2000, $summary[$b->id]['spent']);
        $this->assertSame(0, $summary[$b->id]['owed']);
        $this->assertSame(0, $summary[$a->id]['spent']);
        $this->assertSame(2000, $summary[$a->id]['owed']);
        $this->assertSame(1000, $summary[$c->id]['spent']);
        $this->assertSame(1000, $summary[$c->id]['owed']);
    }

    public function test_payer_can_report_and_payee_can_confirm(): void
    {
        ['a' => $a, 'b' => $b] = $this->scenario();
        $settlement = Settlement::with(['payer', 'payee'])->first();

        $this->actingAs($a)->post(route('settlements.report', $settlement))->assertRedirect();

        $payment = Payment::first();
        $this->assertSame(PaymentStatus::Reported, $payment->status);
        $this->assertSame(2000, $payment->total_amount);
        $this->assertNull($payment->confirmed_by);
        $this->assertGreaterThan(0, $payment->items()->count());
        $this->assertSame(2000, (int) $payment->items()->sum('amount'));

        $this->actingAs($b)->post(route('payments.confirm', $payment))->assertRedirect();

        $this->assertSame(PaymentStatus::Confirmed, $payment->fresh()->status);
        $this->assertSame($b->id, $payment->fresh()->confirmed_by);
        $this->assertSame(SettlementStatus::Completed, $settlement->fresh()->status);
        $this->assertSame($b->id, $settlement->fresh()->completed_by);
    }

    public function test_only_payer_can_report(): void
    {
        ['b' => $b, 'c' => $c] = $this->scenario();
        $settlement = Settlement::with(['payer', 'payee'])->first();

        $this->actingAs($b)->post(route('settlements.report', $settlement))->assertForbidden();
        $this->actingAs($c)->post(route('settlements.report', $settlement))->assertForbidden();
    }

    public function test_duplicate_report_is_rejected(): void
    {
        ['a' => $a] = $this->scenario();
        $settlement = Settlement::with(['payer', 'payee'])->first();

        $this->actingAs($a)->post(route('settlements.report', $settlement));
        $this->actingAs($a)
            ->post(route('settlements.report', $settlement))
            ->assertSessionHasErrors('settlement');

        $this->assertSame(1, Payment::count());
    }

    public function test_payee_can_reject_a_report(): void
    {
        ['a' => $a, 'b' => $b] = $this->scenario();
        $settlement = Settlement::with(['payer', 'payee'])->first();

        $this->actingAs($a)->post(route('settlements.report', $settlement));
        $payment = Payment::first();

        $this->actingAs($b)->post(route('payments.reject', $payment))->assertRedirect();

        $this->assertSame(PaymentStatus::Cancelled, $payment->fresh()->status);
        $this->assertSame(SettlementStatus::Pending, $settlement->fresh()->status);
        $this->assertSame(0, $payment->items()->count());

        // 差し戻し後は再度報告できる
        $this->actingAs($a)->post(route('settlements.report', $settlement))->assertRedirect();
        $this->assertSame(2, Payment::count());
    }

    public function test_unrelated_user_cannot_confirm(): void
    {
        ['a' => $a, 'c' => $c] = $this->scenario();
        $settlement = Settlement::with(['payer', 'payee'])->first();
        $this->actingAs($a)->post(route('settlements.report', $settlement));
        $payment = Payment::first();

        $this->actingAs($c)->post(route('payments.confirm', $payment))->assertForbidden();
    }

    public function test_event_cannot_be_completed_while_settlements_are_pending(): void
    {
        ['group' => $group, 'event' => $event] = $this->scenario();
        $highest = $group->activeMembers()->wherePivot('role', \App\Enums\GroupRole::HighestResponsible->value)->first();

        $this->actingAs($highest)
            ->post(route('events.advance', $event))
            ->assertSessionHasErrors('event');

        $this->assertSame(EventStatus::Settling, $event->fresh()->status);
    }

    public function test_event_can_be_completed_once_all_settlements_are_done(): void
    {
        ['group' => $group, 'event' => $event, 'a' => $a, 'b' => $b] = $this->scenario();
        $settlement = Settlement::with(['payer', 'payee'])->first();

        $this->actingAs($a)->post(route('settlements.report', $settlement));
        $this->actingAs($b)->post(route('payments.confirm', Payment::first()));

        $highest = $group->activeMembers()->wherePivot('role', \App\Enums\GroupRole::HighestResponsible->value)->first();

        $this->actingAs($highest)->post(route('events.advance', $event))->assertRedirect();

        $this->assertSame(EventStatus::Completed, $event->fresh()->status);
    }

    public function test_settlements_can_be_regenerated_before_completion(): void
    {
        ['group' => $group, 'event' => $event] = $this->scenario();
        $responsible = $group->activeMembers()->wherePivot('role', \App\Enums\GroupRole::Responsible->value)->first()
            ?? $group->activeMembers()->wherePivot('role', \App\Enums\GroupRole::HighestResponsible->value)->first();

        $this->actingAs($responsible)
            ->post(route('settlements.regenerate', $event))
            ->assertRedirect();

        $this->assertSame(1, Settlement::count());
    }

    public function test_settlements_cannot_be_regenerated_after_completion(): void
    {
        ['group' => $group, 'event' => $event, 'a' => $a, 'b' => $b] = $this->scenario();
        $settlement = Settlement::with(['payer', 'payee'])->first();
        $this->actingAs($a)->post(route('settlements.report', $settlement));
        $this->actingAs($b)->post(route('payments.confirm', Payment::first()));

        $responsible = $group->activeMembers()->wherePivot('role', \App\Enums\GroupRole::Responsible->value)->first()
            ?? $group->activeMembers()->wherePivot('role', \App\Enums\GroupRole::HighestResponsible->value)->first();

        $this->actingAs($responsible)
            ->post(route('settlements.regenerate', $event))
            ->assertSessionHasErrors('settlement');
    }

    public function test_shortage_reduces_the_amount_owed(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = [$responsibles[0], $members[0], $members[1]];
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Preparation, $participants);

        // A=2, C=1 の希望に対して 1点しか買えず、Aが2点分の不足を負担する
        $event = $this->runEventToSettlement($event, $participants, [
            [
                'circle' => 'サークルX', 'product' => '新刊', 'price' => 1000,
                'wishes' => [0 => 2, 2 => 1], 'assignee' => 1,
                'purchased' => 1, 'shortages' => [0 => 2],
            ],
        ]);

        $balances = app(SettlementService::class)->balances($event);

        // Cだけが1点(1000円)を受け取り、立替者Bに支払う
        $this->assertSame(-1000, $balances[$participants[2]->id]);
        $this->assertSame(1000, $balances[$participants[1]->id]);
        $this->assertArrayNotHasKey($participants[0]->id, $balances);
    }

    public function test_excess_takeover_is_charged_to_the_taker(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = [$responsibles[0], $members[0], $members[1]];
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Preparation, $participants);

        // 希望は A=1 のみ。担当Bが3点買い、超過2点をCが引き取る
        $event = $this->runEventToSettlement($event, $participants, [
            [
                'circle' => 'サークルX', 'product' => '新刊', 'price' => 1000,
                'wishes' => [0 => 1], 'assignee' => 1,
                'purchased' => 3, 'excess' => 2,
            ],
        ]);

        $balances = app(SettlementService::class)->balances($event);

        $this->assertSame(-1000, $balances[$participants[0]->id]);
        $this->assertSame(-2000, $balances[$participants[2]->id]);
        $this->assertSame(3000, $balances[$participants[1]->id]);
    }

    public function test_confirm_buttons_are_shown_to_the_payee_after_a_report(): void
    {
        ['event' => $event, 'a' => $a, 'b' => $b] = $this->scenario();
        $settlement = Settlement::with(['payer', 'payee'])->first();

        // 報告前は受取確認ボタンが出ない
        $this->actingAs($b)
            ->get(route('settlements.index', $event))
            ->assertOk()
            ->assertDontSee('受け取った');

        $this->actingAs($a)->post(route('settlements.report', $settlement));

        // 報告後、受け取る本人には確認・差し戻しボタンが表示される
        $this->actingAs($b)
            ->get(route('settlements.index', $event))
            ->assertOk()
            ->assertSee('受け取った')
            ->assertSee('まだ受け取っていない');

        // 支払った側には表示されない
        $this->actingAs($a)
            ->get(route('settlements.index', $event))
            ->assertOk()
            ->assertDontSee('まだ受け取っていない');
    }

    public function test_screens_render(): void
    {
        ['event' => $event, 'a' => $a] = $this->scenario();
        $settlement = Settlement::with(['payer', 'payee'])->first();

        $this->actingAs($a)
            ->get(route('settlements.index', $event))
            ->assertOk()
            ->assertSee('¥2,000');

        $this->actingAs($a)
            ->get(route('settlements.show', $settlement))
            ->assertOk()
            ->assertSee('新刊');
    }
}
