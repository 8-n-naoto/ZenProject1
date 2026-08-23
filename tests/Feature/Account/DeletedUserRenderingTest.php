<?php

namespace Tests\Feature\Account;

use App\Enums\EventStatus;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 退会した人が関わる記録があっても、画面が壊れないことを確認する。
 */
class DeletedUserRenderingTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_every_event_screen_survives_a_participant_deleting_their_account(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $owner = $highests[0];
        $leaver = $members[0];
        $other = $members[1];
        $participants = [$owner, $leaver, $other];

        $event = $this->makeEvent($group, $owner, EventStatus::Preparation, $participants);
        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => '夏空スタジオ', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1, 1 => 2, 2 => 1], 'assignee' => 1],
        ]);

        // すべての精算を完了させてから退会する
        $settlements = app(SettlementService::class);
        foreach ($event->settlements()->with(['payer', 'payee'])->get() as $settlement) {
            $payment = $settlements->reportPayment($settlement, $settlement->payer);
            $settlements->confirmPayment($payment, $settlement->payee);
        }

        $leaver->delete();
        $this->assertSoftDeleted('users', ['id' => $leaver->id]);

        $sharedPurchase = $event->sharedPurchases()->firstOrFail();
        $settlement = $event->settlements()->firstOrFail();

        $screens = [
            'events.show' => route('events.show', $event),
            'circles.index' => route('circles.index', $event),
            'purchases.shared.index' => route('purchases.shared.index', $event),
            'purchases.shared.show' => route('purchases.shared.show', $sharedPurchase),
            'purchases.summary' => route('purchases.summary', $event),
            'results.index' => route('results.index', $event),
            'settlements.index' => route('settlements.index', $event),
            'settlements.show' => route('settlements.show', $settlement),
            'settlements.mine' => route('settlements.mine'),
            'histories.index' => route('histories.index', $event),
            'approvals.index' => route('approvals.index', $event),
            'groups.show' => route('groups.show', $group),
            'dashboard' => route('dashboard'),
        ];

        $failures = [];

        // 参加者管理は精算中には開けない仕様なので、別イベントで確認する
        $open = $this->makeEvent($group, $owner, EventStatus::Accepting, $participants);
        $screens['events.members.index'] = route('events.members.index', $open);

        foreach ($screens as $name => $url) {
            $status = $this->actingAs($owner)->get($url)->getStatusCode();

            if ($status !== 200) {
                $failures[] = $name.' → HTTP '.$status;
            }
        }

        $this->assertSame([], $failures, "表示できない画面:\n".implode("\n", $failures));
    }

    public function test_a_deleted_user_is_labelled_in_records(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $owner = $highests[0];
        $leaver = $members[0];
        $participants = [$owner, $leaver, $members[1]];

        $event = $this->makeEvent($group, $owner, EventStatus::Preparation, $participants);
        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => '夏空スタジオ', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1, 1 => 2, 2 => 1], 'assignee' => 1],
        ]);

        $leaver->delete();

        $this->actingAs($owner)->get(route('settlements.index', $event))
            ->assertOk()
            ->assertSee('（退会済み）');
    }
}
