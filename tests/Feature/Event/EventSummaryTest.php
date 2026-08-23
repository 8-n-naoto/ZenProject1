<?php

namespace Tests\Feature\Event;

use App\Enums\EventStatus;
use App\Models\SharedPurchase;
use App\Services\PurchaseListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class EventSummaryTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_summary_shows_wishes_and_assignments(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = array_merge($responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);
        ['products' => $products] = $this->makeCatalog($event, '夏空スタジオ', [['name' => '新刊', 'price' => 1200]]);

        $purchases = app(PurchaseListService::class);
        $purchases->savePersonalPurchases($event, $members[0], [$products[0]->id => 2]);
        $purchases->syncAll($event, $responsibles[0]);
        $purchases->assign(SharedPurchase::first(), $members[0], $responsibles[0]);

        $this->actingAs($members[0])
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('あなたの状況')
            ->assertSee('¥2,400')
            ->assertSee('購入希望');
    }

    public function test_summary_counts_pending_results(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = array_merge($responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);
        ['products' => $products] = $this->makeCatalog($event);

        $purchases = app(PurchaseListService::class);
        $purchases->savePersonalPurchases($event, $members[1], [$products[0]->id => 1]);
        $purchases->syncAll($event, $responsibles[0]);
        $purchases->assign(SharedPurchase::first(), $members[0], $responsibles[0]);

        $event->update(['status' => EventStatus::Ongoing, 'fixed_at' => now()]);

        $summary = app(\App\Services\EventSummaryService::class)->forUser($event->fresh(), $members[0]);

        $this->assertSame(1, $summary['assignedCircles']);
        $this->assertSame(1, $summary['pendingResults']);
        $this->assertSame(0, $summary['wishCount']);
    }

    public function test_summary_shows_settlement_direction(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = [$responsibles[0], $members[0], $members[1]];
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Preparation, $participants);
        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => 'X', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1], 'assignee' => 1],
        ]);

        $payer = $participants[0];
        $payee = $participants[1];

        $this->actingAs($payer)->get(route('events.show', $event))->assertOk()->assertSee('支払い');
        $this->actingAs($payee)->get(route('events.show', $event))->assertOk()->assertSee('受け取り');
    }
}
