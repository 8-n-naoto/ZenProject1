<?php

namespace Tests\Feature\Purchase;

use App\Enums\EventStatus;
use App\Enums\PurchaseResultStatus;
use App\Models\PurchaseResult;
use App\Models\SharedPurchase;
use App\Models\SharedPurchaseItem;
use App\Services\CatalogService;
use App\Services\PurchaseListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class ShoppingListTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    /**
     * 3サークル・担当は buyer（members[0]）というシナリオ。
     */
    private function scenario(EventStatus $status = EventStatus::Ongoing): array
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = array_merge($responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);

        $catalog = app(CatalogService::class);
        $purchases = app(PurchaseListService::class);

        $plan = [
            ['西2 サ-05b', '星屑レコード', 'CD', 1200],
            ['東1 ア-12a', '夏空スタジオ', '新刊', 1000],
            ['東1 ア-03b', 'ねこまた工房', '短編集', 700],
        ];

        $products = [];

        foreach ($plan as [$booth, $name, $productName, $price]) {
            $circle = $catalog->createCircle($event, ['display_name' => $name, 'booth' => $booth]);
            $products[$name] = $catalog->createProduct($circle, ['name' => $productName, 'price' => $price]);
        }

        foreach ($products as $product) {
            $purchases->savePersonalPurchases($event, $members[1], [$product->id => 1]);
        }

        $purchases->syncAll($event, $responsibles[0]);

        foreach ($event->fresh()->sharedPurchases as $sharedPurchase) {
            $purchases->assign($sharedPurchase, $members[0], $responsibles[0]);
        }

        $event->update(['status' => $status, 'fixed_at' => now()]);

        return compact('group', 'responsibles', 'members', 'event', 'products');
    }

    public function test_shopping_list_shows_assigned_circles_in_booth_order(): void
    {
        ['event' => $event, 'members' => $members] = $this->scenario();

        $response = $this->actingAs($members[0])->get(route('shopping.index', $event))->assertOk();

        $content = $response->getContent();
        $positions = [
            'ねこまた工房' => mb_strpos($content, 'ねこまた工房'),
            '夏空スタジオ' => mb_strpos($content, '夏空スタジオ'),
            '星屑レコード' => mb_strpos($content, '星屑レコード'),
        ];

        // 東1 ア-03b → 東1 ア-12a → 西2 サ-05b の順
        $this->assertLessThan($positions['夏空スタジオ'], $positions['ねこまた工房']);
        $this->assertLessThan($positions['星屑レコード'], $positions['夏空スタジオ']);
    }

    public function test_non_assignee_sees_an_empty_list(): void
    {
        ['event' => $event, 'members' => $members] = $this->scenario();

        $this->actingAs($members[1])
            ->get(route('shopping.index', $event))
            ->assertOk()
            ->assertSee('あなたが回るサークルはありません');
    }

    public function test_one_tap_records_a_purchase_as_planned(): void
    {
        ['event' => $event, 'members' => $members] = $this->scenario();
        $item = SharedPurchaseItem::first();

        $this->actingAs($members[0])
            ->post(route('shopping.items.planned', $item))
            ->assertRedirect();

        $result = PurchaseResult::first();
        $this->assertSame(1, $result->purchased_quantity);
        $this->assertSame(PurchaseResultStatus::Completed, $result->status);
        $this->assertSame($members[0]->id, $result->purchase_assignee_user_id);
    }

    public function test_one_tap_records_a_sold_out_item(): void
    {
        ['event' => $event, 'members' => $members] = $this->scenario();
        $item = SharedPurchaseItem::first();

        $this->actingAs($members[0])
            ->post(route('shopping.items.sold-out', $item))
            ->assertRedirect();

        $result = PurchaseResult::first();
        $this->assertSame(0, $result->purchased_quantity);
        $this->assertSame(PurchaseResultStatus::Shortage, $result->status);
        $this->assertSame(1, (int) $result->shortageUsers()->sum('shortage_quantity'));
    }

    public function test_a_whole_circle_can_be_marked_at_once(): void
    {
        ['event' => $event, 'members' => $members, 'responsibles' => $responsibles] = $this->scenario();

        // 1サークルに2商品ある状態を作る
        $sharedPurchase = SharedPurchase::first();
        $circle = $sharedPurchase->eventCircle;
        $extra = app(CatalogService::class)->createProduct($circle, ['name' => '追加グッズ', 'price' => 300]);
        app(PurchaseListService::class)->savePersonalPurchases($event, $members[1], [$extra->id => 2]);

        $event->update(['status' => EventStatus::Accepting]);
        app(PurchaseListService::class)->syncAll($event->fresh(), $responsibles[0]);
        $event->update(['status' => EventStatus::Ongoing]);

        $this->actingAs($members[0])
            ->post(route('shopping.circles.planned', $sharedPurchase))
            ->assertRedirect();

        $this->assertSame(2, PurchaseResult::count());
    }

    public function test_only_the_assignee_can_use_one_tap_recording(): void
    {
        ['members' => $members] = $this->scenario();
        $item = SharedPurchaseItem::first();

        $this->actingAs($members[1])
            ->post(route('shopping.items.planned', $item))
            ->assertForbidden();
    }

    public function test_personal_items_can_be_recorded(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $participants = array_merge($responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);
        ['products' => $products] = $this->makeCatalog($event);

        app(PurchaseListService::class)->savePersonalPurchases($event, $members[0], [$products[0]->id => 2]);
        $event->update(['status' => EventStatus::Ongoing, 'fixed_at' => now()]);

        $purchase = \App\Models\PersonalPurchase::first();

        $this->actingAs($members[0])
            ->get(route('shopping.index', $event->fresh()))
            ->assertOk()
            ->assertSee('自分で買う分');

        $this->actingAs($members[0])
            ->post(route('shopping.personal', [$purchase, 'bought']))
            ->assertRedirect();

        $this->assertSame(2, PurchaseResult::first()->purchased_quantity);
    }

    public function test_progress_is_shown(): void
    {
        ['event' => $event, 'members' => $members] = $this->scenario();
        $item = SharedPurchaseItem::first();
        $this->actingAs($members[0])->post(route('shopping.items.planned', $item));

        $this->actingAs($members[0])
            ->get(route('shopping.index', $event))
            ->assertOk()
            ->assertSee('1 / 3 件');
    }

    public function test_recording_during_settlement_regenerates_settlements(): void
    {
        ['event' => $event, 'members' => $members] = $this->scenario(EventStatus::Ongoing);

        foreach (SharedPurchaseItem::all() as $item) {
            $this->actingAs($members[0])->post(route('shopping.items.planned', $item));
        }

        app(\App\Services\EventService::class)->advance($event->fresh());
        $this->assertSame(EventStatus::Settling, $event->fresh()->status);

        $before = (int) \App\Models\Settlement::sum('amount');
        $this->assertSame(2900, $before);
    }
}
