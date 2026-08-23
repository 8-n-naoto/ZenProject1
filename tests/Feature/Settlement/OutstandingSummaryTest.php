<?php

namespace Tests\Feature\Settlement;

use App\Enums\EventStatus;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class OutstandingSummaryTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_outstanding_totals_span_every_group(): void
    {
        ['group' => $groupA, 'highest' => $highestsA, 'member' => $membersA] = $this->makeGroup();
        $buyer = $highestsA[0];
        $friend = $membersA[0];

        $eventA = $this->makeEvent($groupA, $buyer, EventStatus::Preparation, [$buyer, $friend]);
        $this->runEventToSettlement($eventA, [$buyer, $friend], [
            ['circle' => '夏空スタジオ', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1, 1 => 2], 'assignee' => 0],
        ]);

        // 別グループでは立場が逆になる
        ['group' => $groupB] = $this->makeGroup(['highest' => 0, 'responsible' => 0, 'member' => 0]);
        $groupB->members()->attach($friend->id, ['role' => \App\Enums\GroupRole::HighestResponsible->value, 'joined_at' => now()]);
        $groupB->members()->attach($buyer->id, ['role' => \App\Enums\GroupRole::Member->value, 'joined_at' => now()]);

        $eventB = $this->makeEvent($groupB, $friend, EventStatus::Preparation, [$friend, $buyer]);
        $this->runEventToSettlement($eventB, [$friend, $buyer], [
            ['circle' => '冬空スタジオ', 'product' => '画集', 'price' => 500, 'wishes' => [0 => 1, 1 => 1], 'assignee' => 0],
        ]);

        $summary = app(SettlementService::class)->outstandingFor($buyer->fresh());

        $this->assertSame(2000, $summary['receiveTotal']);   // groupA: friend が 2冊分
        $this->assertSame(500, $summary['payTotal']);        // groupB: friend へ 1冊分
        $this->assertSame(1500, $summary['net']);
        $this->assertCount(1, $summary['toPay']);
        $this->assertCount(1, $summary['toReceive']);
    }

    public function test_completed_settlements_are_excluded(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $buyer = $highests[0];
        $friend = $members[0];

        $event = $this->makeEvent($group, $buyer, EventStatus::Preparation, [$buyer, $friend]);
        $event = $this->runEventToSettlement($event, [$buyer, $friend], [
            ['circle' => '夏空スタジオ', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1, 1 => 1], 'assignee' => 0],
        ]);

        $settlements = app(SettlementService::class);
        $settlement = $event->settlements()->first();
        $payment = $settlements->reportPayment($settlement, $friend);
        $settlements->confirmPayment($payment, $buyer);

        $summary = $settlements->outstandingFor($buyer->fresh());

        $this->assertSame(0, $summary['receiveTotal']);
        $this->assertCount(0, $summary['toReceive']);
    }

    public function test_page_and_dashboard_show_the_summary(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $buyer = $highests[0];
        $friend = $members[0];

        $event = $this->makeEvent($group, $buyer, EventStatus::Preparation, [$buyer, $friend]);
        $this->runEventToSettlement($event, [$buyer, $friend], [
            ['circle' => '夏空スタジオ', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1, 1 => 3], 'assignee' => 0],
        ]);

        $this->actingAs($buyer)->get(route('settlements.mine'))
            ->assertOk()
            ->assertSee('未精算のまとめ')
            ->assertSee('¥3,000');

        $this->actingAs($buyer)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('未精算のまとめ');
    }

    public function test_member_with_an_unpaid_settlement_cannot_leave_or_be_removed(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $buyer = $highests[0];
        $friend = $members[0];

        $event = $this->makeEvent($group, $buyer, EventStatus::Preparation, [$buyer, $friend]);
        $this->runEventToSettlement($event, [$buyer, $friend], [
            ['circle' => '夏空スタジオ', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1, 1 => 1], 'assignee' => 0],
        ]);

        $members2 = app(\App\Services\GroupMemberService::class);

        try {
            $members2->leave($group, $friend);
            $this->fail('未精算があるのに脱退できてしまいました。');
        } catch (\App\Exceptions\BusinessRuleException $e) {
            $this->assertStringContainsString('未精算', $e->getMessage());
        }

        try {
            $members2->remove($group, $friend);
            $this->fail('未精算があるのに除名できてしまいました。');
        } catch (\App\Exceptions\BusinessRuleException $e) {
            $this->assertStringContainsString('未精算', $e->getMessage());
        }

        // 退会も同じ理由でブロックされる
        $reasons = app(\App\Services\AccountDeletionGuard::class)->reasons($friend->fresh());
        $this->assertNotEmpty(array_filter($reasons, fn (string $r) => str_contains($r, '未精算')));
    }

    public function test_leaving_is_allowed_once_everything_is_settled(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $buyer = $highests[0];
        $friend = $members[0];

        $event = $this->makeEvent($group, $buyer, EventStatus::Preparation, [$buyer, $friend]);
        $event = $this->runEventToSettlement($event, [$buyer, $friend], [
            ['circle' => '夏空スタジオ', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1, 1 => 1], 'assignee' => 0],
        ]);

        $settlements = app(SettlementService::class);
        foreach ($event->settlements as $settlement) {
            $payment = $settlements->reportPayment($settlement, $settlement->payer);
            $settlements->confirmPayment($payment, $settlement->payee);
        }

        app(\App\Services\GroupMemberService::class)->leave($group, $friend);

        $this->assertFalse($group->fresh()->isActiveMember($friend));
    }

    public function test_summary_is_empty_for_a_user_with_no_settlements(): void
    {
        $user = \App\Models\User::factory()->create();

        $summary = app(SettlementService::class)->outstandingFor($user);

        $this->assertSame(0, $summary['payTotal']);
        $this->assertSame(0, $summary['receiveTotal']);

        $this->actingAs($user)->get(route('settlements.mine'))
            ->assertOk()
            ->assertSee('未精算はありません');
    }
}
