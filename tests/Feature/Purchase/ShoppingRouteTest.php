<?php

namespace Tests\Feature\Purchase;

use App\Enums\EventStatus;
use App\Enums\SelloutRisk;
use App\Exceptions\BusinessRuleException;
use App\Models\Event;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\PurchaseListService;
use App\Services\ShoppingListService;
use App\Services\ShoppingRouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class ShoppingRouteTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    /**
     * 3サークルを担当している「開催中」のイベントを作る。
     *
     * @return array{event: Event, buyer: User, circles: array<string, int>}
     */
    private function ongoingEvent(array $risks = []): array
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $buyer = $highests[0];
        $wanter = $members[0];
        $participants = [$buyer, $wanter];

        $event = $this->makeEvent($group, $buyer, EventStatus::Accepting, $participants);

        $catalog = app(CatalogService::class);
        $purchases = app(PurchaseListService::class);
        $ids = [];

        // わざと配置順と登録順を食い違わせる
        $plan = [
            'C社' => '東3 サ-30a',
            'A社' => '東1 ア-10a',
            'B社' => '東2 ウ-20a',
        ];

        foreach ($plan as $name => $booth) {
            $circle = $catalog->createCircle($event, [
                'display_name' => $name,
                'booth' => $booth,
                'sellout_risk' => $risks[$name] ?? null,
            ]);
            $product = $catalog->createProduct($circle, ['name' => $name.'の新刊', 'price' => 1000]);

            $purchases->savePersonalPurchases($event, $wanter, [$product->id => 1]);
            $shared = $purchases->syncSharedPurchaseFromWishes($circle->fresh(), $buyer);
            $purchases->assign($shared, $buyer, $buyer);

            $ids[$name] = $circle->id;
        }

        $event->update(['status' => EventStatus::Ongoing, 'fixed_at' => now()]);

        return ['event' => $event->fresh(), 'buyer' => $buyer, 'circles' => $ids];
    }

    private function orderOf(Event $event, User $user): array
    {
        return app(ShoppingListService::class)
            ->forUser($event, $user)['circles']
            ->map(fn (array $row) => $row['circle']->display_name)
            ->all();
    }

    public function test_the_default_route_follows_booth_order(): void
    {
        ['event' => $event, 'buyer' => $buyer] = $this->ongoingEvent();

        $this->assertSame(['A社', 'B社', 'C社'], $this->orderOf($event, $buyer));
    }

    public function test_circles_that_sell_out_come_first(): void
    {
        ['event' => $event, 'buyer' => $buyer] = $this->ongoingEvent([
            'C社' => SelloutRisk::High->value,
            'A社' => SelloutRisk::Low->value,
        ]);

        // C社は配置が最後だが、完売しやすいので先頭に来る
        $this->assertSame(['C社', 'B社', 'A社'], $this->orderOf($event, $buyer));
    }

    public function test_a_manual_order_wins_over_the_suggestion(): void
    {
        ['event' => $event, 'buyer' => $buyer, 'circles' => $ids] = $this->ongoingEvent();

        $this->actingAs($buyer)
            ->patch(route('shopping.route.save', $event), [
                'circles' => [$ids['B社'], $ids['C社'], $ids['A社']],
            ])
            ->assertRedirect();

        $this->assertSame(['B社', 'C社', 'A社'], $this->orderOf($event->fresh(), $buyer));

        $this->actingAs($buyer)->get(route('shopping.index', $event->fresh()))
            ->assertOk()
            ->assertSee('自分で並べ替えた順で表示しています');
    }

    public function test_resetting_returns_to_the_suggested_order(): void
    {
        ['event' => $event, 'buyer' => $buyer, 'circles' => $ids] = $this->ongoingEvent();

        app(ShoppingRouteService::class)->save($event, $buyer, [$ids['B社'], $ids['C社'], $ids['A社']]);

        $this->actingAs($buyer)
            ->delete(route('shopping.route.reset', $event))
            ->assertRedirect();

        $this->assertSame(['A社', 'B社', 'C社'], $this->orderOf($event->fresh(), $buyer));
    }

    public function test_a_circle_added_after_the_reorder_goes_to_the_end(): void
    {
        ['event' => $event, 'buyer' => $buyer, 'circles' => $ids] = $this->ongoingEvent();

        // A社とB社だけ並べ替えた状態で、C社は保存対象に入っていない
        app(ShoppingRouteService::class)->save($event, $buyer, [$ids['B社'], $ids['A社']]);

        $this->assertSame(['B社', 'A社', 'C社'], $this->orderOf($event->fresh(), $buyer));
    }

    public function test_route_of_another_event_is_ignored(): void
    {
        ['event' => $event, 'buyer' => $buyer] = $this->ongoingEvent();
        $other = $this->makeEvent($event->group, $buyer, EventStatus::Accepting, [$buyer]);

        $this->expectException(BusinessRuleException::class);
        app(ShoppingRouteService::class)->save($event, $buyer, [$other->id + 9999]);
    }

    public function test_the_route_can_be_shared_as_text(): void
    {
        ['event' => $event, 'buyer' => $buyer] = $this->ongoingEvent([
            'A社' => SelloutRisk::High->value,
        ]);

        $list = app(ShoppingListService::class)->forUser($event, $buyer);
        $text = app(ShoppingRouteService::class)->shareText($event, $buyer, $list['circles']);

        $this->assertStringContainsString($event->name, $text);
        $this->assertStringContainsString('1. 東1 ア-10a A社（早めに）', $text);
        $this->assertStringContainsString('A社の新刊 × 1点', $text);

        $this->actingAs($buyer)->get(route('shopping.index', $event))
            ->assertOk()
            ->assertSee('ルートをコピーする');
    }

    public function test_a_non_participant_cannot_save_a_route(): void
    {
        ['event' => $event, 'circles' => $ids] = $this->ongoingEvent();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->patch(route('shopping.route.save', $event), ['circles' => [$ids['A社']]])
            ->assertForbidden();
    }

    public function test_each_participant_keeps_their_own_route(): void
    {
        ['event' => $event, 'buyer' => $buyer, 'circles' => $ids] = $this->ongoingEvent();
        $other = $event->participants->firstWhere('id', '!=', $buyer->id);

        app(ShoppingRouteService::class)->save($event, $buyer, [$ids['C社'], $ids['A社'], $ids['B社']]);

        $this->assertSame([$ids['C社'], $ids['A社'], $ids['B社']], app(ShoppingRouteService::class)->savedOrder($event, $buyer));
        $this->assertSame([], app(ShoppingRouteService::class)->savedOrder($event, $other));
    }
}
