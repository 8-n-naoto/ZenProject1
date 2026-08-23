<?php

namespace Tests\Feature\Settlement;

use App\Enums\EventStatus;
use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 収支の内訳ページ（メンバー1人分の立替・購入の明細）。
 */
class SettlementBreakdownTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    /**
     * SettlementTest と同じシナリオ。参加者3人 A(責任者)/B/C。
     *  - サークルX 新刊1000円: A=1, B=2, C=1 を B が購入（Bが4000円立替）
     *  - サークルY グッズ500円: A=2, C=2 を C が購入（Cが2000円立替）
     * 債務: A→B 1000 / C→B 1000 / A→C 1000
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

    public function test_breakdown_totals_match_summary(): void
    {
        ['event' => $event, 'a' => $a, 'b' => $b, 'c' => $c] = $this->scenario();

        $service = app(SettlementService::class);

        $forB = $service->breakdownFor($event, $b);
        $this->assertSame(2000, $forB['spentTotal']);
        $this->assertSame(0, $forB['owedTotal']);
        $this->assertSame(2000, $forB['net']);

        $forC = $service->breakdownFor($event, $c);
        $this->assertSame(1000, $forC['spentTotal']);
        $this->assertSame(1000, $forC['owedTotal']);
        $this->assertSame(0, $forC['net']);

        $forA = $service->breakdownFor($event, $a);
        $this->assertSame(0, $forA['spentTotal']);
        $this->assertSame(2000, $forA['owedTotal']);
        $this->assertSame(-2000, $forA['net']);
    }

    public function test_breakdown_lists_each_debt_with_product_and_counterparty(): void
    {
        ['event' => $event, 'a' => $a, 'b' => $b, 'c' => $c] = $this->scenario();

        $forB = app(SettlementService::class)->breakdownFor($event, $b);

        // Bの立替: Aの新刊1点 と Cの新刊1点（B自身の2点は含まれない）
        $spent = $forB['spent']->map(fn (array $row) => [
            'user' => $row['counterparty']->id,
            'product' => $row['result']->eventProduct->name,
            'quantity' => $row['quantity'],
            'amount' => $row['amount'],
        ])->sortBy('user')->values()->all();

        $expected = collect([
            ['user' => $a->id, 'product' => '新刊', 'quantity' => 1, 'amount' => 1000],
            ['user' => $c->id, 'product' => '新刊', 'quantity' => 1, 'amount' => 1000],
        ])->sortBy('user')->values()->all();

        $this->assertSame($expected, $spent);
        $this->assertTrue($forB['owed']->isEmpty());

        // Aの購入分: Bが立て替えた新刊1点 と Cが立て替えたグッズ2点
        $forA = app(SettlementService::class)->breakdownFor($event, $a);
        $owed = $forA['owed']->map(fn (array $row) => [
            'user' => $row['counterparty']->id,
            'product' => $row['result']->eventProduct->name,
            'quantity' => $row['quantity'],
            'amount' => $row['amount'],
        ])->sortBy('user')->values()->all();

        $expectedOwed = collect([
            ['user' => $b->id, 'product' => '新刊', 'quantity' => 1, 'amount' => 1000],
            ['user' => $c->id, 'product' => 'グッズ', 'quantity' => 2, 'amount' => 1000],
        ])->sortBy('user')->values()->all();

        $this->assertSame($expectedOwed, $owed);
    }

    public function test_breakdown_page_renders_for_participant(): void
    {
        ['event' => $event, 'a' => $a, 'b' => $b] = $this->scenario();

        $response = $this->actingAs($a)->get(route('settlements.breakdown', [$event, $b]));

        $response->assertOk()
            ->assertSee($b->displayName())
            ->assertSee('立て替えたもの')
            ->assertSee('購入したもの')
            ->assertSee('新刊')
            ->assertSee('サークルX')
            ->assertSee('+¥2,000');
    }

    public function test_summary_card_links_to_breakdown(): void
    {
        ['event' => $event, 'a' => $a, 'b' => $b] = $this->scenario();

        $this->actingAs($a)->get(route('settlements.index', $event))
            ->assertOk()
            ->assertSee(route('settlements.breakdown', [$event, $b]));
    }

    public function test_outsider_cannot_view_breakdown(): void
    {
        ['event' => $event, 'b' => $b] = $this->scenario();

        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('settlements.breakdown', [$event, $b]))
            ->assertForbidden();
    }
}
