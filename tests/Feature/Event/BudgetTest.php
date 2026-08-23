<?php

namespace Tests\Feature\Event;

use App\Enums\EventStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\PurchaseListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_a_participant_can_set_and_clear_their_budget(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $user = $highests[0];
        $event = $this->makeEvent($group, $user, EventStatus::Accepting, [$user]);

        $this->actingAs($user)
            ->patch(route('events.budget.update', $event), ['budget' => 20000])
            ->assertRedirect();

        $this->assertSame(20000, app(BudgetService::class)->budgetOf($event->fresh(), $user));

        $this->actingAs($user)
            ->patch(route('events.budget.update', $event), ['budget' => null])
            ->assertRedirect();

        $this->assertNull(app(BudgetService::class)->budgetOf($event->fresh(), $user));
    }

    public function test_remaining_reflects_planned_purchases_before_the_event(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $user = $highests[0];
        $event = $this->makeEvent($group, $user, EventStatus::Accepting, [$user]);
        ['products' => $products] = $this->makeCatalog($event, '夏空スタジオ', [
            ['name' => '新刊', 'price' => 1500],
            ['name' => '既刊', 'price' => 800],
        ]);

        $budgets = app(BudgetService::class);
        $budgets->set($event, $user, 5000);

        app(PurchaseListService::class)->savePersonalPurchases($event, $user, [
            $products[0]->id => 2,   // 3000
            $products[1]->id => 1,   //  800
        ]);

        $status = $budgets->statusFor($event->fresh(), $user);

        $this->assertSame('planned', $status['basis']);
        $this->assertSame(3800, $status['planned']);
        $this->assertSame(1200, $status['remaining']);
        $this->assertFalse($status['isOver']);
    }

    public function test_going_over_the_budget_is_flagged_and_shown(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $user = $highests[0];
        $event = $this->makeEvent($group, $user, EventStatus::Accepting, [$user]);
        ['products' => $products] = $this->makeCatalog($event, '夏空スタジオ', [['name' => '新刊', 'price' => 1500]]);

        $budgets = app(BudgetService::class);
        $budgets->set($event, $user, 2000);
        app(PurchaseListService::class)->savePersonalPurchases($event, $user, [$products[0]->id => 3]);

        $status = $budgets->statusFor($event->fresh(), $user);

        $this->assertTrue($status['isOver']);
        $this->assertSame(-2500, $status['remaining']);

        $this->actingAs($user)->get(route('purchases.personal.index', $event))
            ->assertOk()
            ->assertSee('予算を超えています');
    }

    public function test_remaining_switches_to_actual_spend_on_the_day(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $buyer = $highests[0];
        $me = $members[0];
        $participants = [$buyer, $me, $members[1]];

        $event = $this->makeEvent($group, $buyer, EventStatus::Preparation, $participants);

        $budgets = app(BudgetService::class);
        $event = $this->runEventToSettlement($event, $participants, [
            // 自分は2点欲しくて、実際に全部買えた（1点1000円）
            ['circle' => '夏空スタジオ', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1, 1 => 2, 2 => 1], 'assignee' => 0],
        ]);

        $budgets->set($event->fresh(), $me, 5000);
        $status = $budgets->statusFor($event->fresh(), $me);

        $this->assertSame('actual', $status['basis']);
        $this->assertSame(2000, $status['spent'], '自分の負担額（2点 × 1000円）');
        $this->assertSame(3000, $status['remaining']);
    }

    public function test_actual_spend_counts_your_share_even_when_someone_else_paid(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $buyer = $highests[0];
        $participants = [$buyer, $members[0], $members[1]];

        $event = $this->makeEvent($group, $buyer, EventStatus::Preparation, $participants);
        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => '夏空スタジオ', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 3, 1 => 1, 2 => 1], 'assignee' => 0],
        ]);

        $budgets = app(BudgetService::class);

        // 立て替えた本人も、自分の取り分だけが「使った額」になる
        $this->assertSame(3000, $budgets->spentAmount($event->fresh(), $buyer));
        $this->assertSame(1000, $budgets->spentAmount($event->fresh(), $members[0]));
    }

    public function test_shortages_reduce_the_amount_you_used(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $buyer = $highests[0];
        $me = $members[0];
        $participants = [$buyer, $me, $members[1]];

        $event = $this->makeEvent($group, $buyer, EventStatus::Preparation, $participants);
        $event = $this->runEventToSettlement($event, $participants, [
            [
                'circle' => '夏空スタジオ', 'product' => '新刊', 'price' => 1000,
                'wishes' => [0 => 1, 1 => 2, 2 => 1],
                'assignee' => 0,
                'purchased' => 3,          // 4点欲しかったが3点しか買えなかった
                'shortages' => [1 => 1],   // 自分の分が1点不足
            ],
        ]);

        // 2点欲しくて1点足りない → 1点分（1000円）だけ使ったことになる
        $this->assertSame(1000, app(BudgetService::class)->spentAmount($event->fresh(), $me));
    }

    public function test_a_non_participant_cannot_set_a_budget(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting, [$highests[0]]);

        $this->expectException(BusinessRuleException::class);
        app(BudgetService::class)->set($event, $members[0], 1000);
    }

    public function test_an_outsider_cannot_touch_the_budget_endpoint(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting, [$highests[0]]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->patch(route('events.budget.update', $event), ['budget' => 1000])
            ->assertForbidden();
    }

    public function test_out_of_range_budgets_are_rejected(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $user = $highests[0];
        $event = $this->makeEvent($group, $user, EventStatus::Accepting, [$user]);

        $this->actingAs($user)
            ->patch(route('events.budget.update', $event), ['budget' => -1])
            ->assertSessionHasErrors('budget');

        $this->actingAs($user)
            ->patch(route('events.budget.update', $event), ['budget' => 99999999])
            ->assertSessionHasErrors('budget');
    }

    public function test_the_shopping_list_always_shows_the_remaining_amount(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $buyer = $highests[0];
        $participants = [$buyer, $members[0], $members[1]];

        $event = $this->makeEvent($group, $buyer, EventStatus::Preparation, $participants);
        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => '夏空スタジオ', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1, 1 => 1, 2 => 1], 'assignee' => 0],
        ]);

        app(BudgetService::class)->set($event->fresh(), $buyer, 10000);
        $event->update(['status' => EventStatus::Ongoing]);

        $this->actingAs($buyer)->get(route('shopping.index', $event->fresh()))
            ->assertOk()
            ->assertSee('残り（予算 ¥10,000）')
            ->assertSee('¥9,000');
    }
}
