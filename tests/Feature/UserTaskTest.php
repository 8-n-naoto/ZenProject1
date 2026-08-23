<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use App\Services\PurchaseListService;
use App\Services\UserTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class UserTaskTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private function keys(User $user): array
    {
        return app(UserTaskService::class)->pendingFor($user)->pluck('key')->all();
    }

    public function test_pending_invitation_is_listed(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $target = User::factory()->create();
        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $target]));

        $this->assertContains('invitations', $this->keys($target));
    }

    public function test_group_without_responsible_is_listed_for_the_owner(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('groups.store'), ['name' => 'ひとりグループ']);
        $group = Group::firstWhere('name', 'ひとりグループ');

        $this->assertContains('responsible:'.$group->id, $this->keys($user));
    }

    public function test_unjoined_accepting_event_is_listed(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting);

        $this->assertContains('join:'.$event->id, $this->keys($members[0]));
    }

    public function test_missing_wishes_are_listed_for_participants(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $members);

        $this->assertContains('wish:'.$event->id, $this->keys($members[0]));

        ['products' => $products] = $this->makeCatalog($event);
        app(PurchaseListService::class)->savePersonalPurchases($event, $members[0], [$products[0]->id => 1]);

        $this->assertNotContains('wish:'.$event->id, $this->keys($members[0]));
    }

    public function test_unrecorded_results_are_listed_for_the_assignee(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = array_merge($responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);
        ['products' => $products] = $this->makeCatalog($event);

        $purchases = app(PurchaseListService::class);
        $purchases->savePersonalPurchases($event, $members[1], [$products[0]->id => 1]);
        $purchases->syncAll($event, $responsibles[0]);
        $sharedPurchase = $event->fresh()->sharedPurchases->first();
        $purchases->assign($sharedPurchase, $members[0], $responsibles[0]);

        $event->update(['status' => EventStatus::Ongoing, 'fixed_at' => now()]);

        $this->assertContains('shopping:'.$event->id, $this->keys($members[0]));
        $this->assertNotContains('shopping:'.$event->id, $this->keys($members[1]));
    }

    public function test_settlement_tasks_are_listed_for_both_sides(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = [$responsibles[0], $members[0], $members[1]];
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Preparation, $participants);
        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => 'X', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1], 'assignee' => 1],
        ]);

        $settlement = Settlement::with(['payer', 'payee'])->first();

        $this->assertContains('pay:'.$settlement->id, $this->keys($settlement->payer));
        $this->assertNotContains('confirm:'.$settlement->id, $this->keys($settlement->payee));

        $this->actingAs($settlement->payer)->post(route('settlements.report', $settlement));

        $this->assertNotContains('pay:'.$settlement->id, $this->keys($settlement->payer->fresh()));
        $this->assertContains('confirm:'.$settlement->id, $this->keys($settlement->payee->fresh()));
    }

    public function test_pending_approval_is_listed_for_approvers_who_have_not_voted(): void
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['responsible' => 2]);
        $participants = array_merge($highests, $responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);

        $this->actingAs($responsibles[0])->post(route('events.advance', $event));

        $this->assertContains('approval:1', $this->keys($responsibles[1]));
        $this->assertNotContains('approval:1', $this->keys($responsibles[0]));
        $this->assertNotContains('approval:1', $this->keys($members[0]));
    }

    public function test_dashboard_shows_tasks(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting);

        $this->actingAs($members[0])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('やること')
            ->assertSee('参加するか決める');
    }

    public function test_dashboard_is_clean_when_there_is_nothing_to_do(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('対応が必要なことはありません');
    }
}
