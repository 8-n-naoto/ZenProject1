<?php

namespace Tests\Feature\Purchase;

use App\Enums\EventStatus;
use App\Services\CatalogService;
use App\Services\PurchaseListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 購入まわりの画面が配置順（当日回る順）で並ぶことを確認する。
 */
class BoothOrderingTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private function scenario(): array
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = array_merge($responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);

        $catalog = app(CatalogService::class);
        $purchases = app(PurchaseListService::class);

        foreach ([
            ['星屑レコード', '西1 サ-31a', 'CD'],
            ['ねこまた工房', '東2 ウ-05b', '短編集'],
            ['夏空スタジオ', '東1 ア-12a', '新刊'],
        ] as [$name, $booth, $productName]) {
            $circle = $catalog->createCircle($event, ['display_name' => $name, 'booth' => $booth]);
            $product = $catalog->createProduct($circle, ['name' => $productName, 'price' => 1000]);
            $purchases->savePersonalPurchases($event, $members[1], [$product->id => 1]);
        }

        $purchases->syncAll($event, $responsibles[0]);

        return compact('group', 'responsibles', 'members', 'event');
    }

    private function assertBoothOrder(string $content): void
    {
        $natsuzora = mb_strpos($content, '夏空スタジオ');
        $nekomata = mb_strpos($content, 'ねこまた工房');
        $hoshikuzu = mb_strpos($content, '星屑レコード');

        $this->assertNotFalse($natsuzora);
        $this->assertLessThan($nekomata, $natsuzora, '東1 が 東2 より先に来ること');
        $this->assertLessThan($hoshikuzu, $nekomata, '東2 が 西1 より先に来ること');
    }

    public function test_wish_screen_is_in_booth_order(): void
    {
        ['event' => $event, 'members' => $members] = $this->scenario();

        $this->assertBoothOrder(
            $this->actingAs($members[1])->get(route('purchases.personal.index', $event))->getContent()
        );
    }

    public function test_summary_screen_is_in_booth_order(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $this->assertBoothOrder(
            $this->actingAs($responsibles[0])->get(route('purchases.summary', $event))->getContent()
        );
    }

    public function test_shared_purchase_list_is_in_booth_order(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $this->assertBoothOrder(
            $this->actingAs($responsibles[0])->get(route('purchases.shared.index', $event))->getContent()
        );
    }

    public function test_catalog_screen_is_in_booth_order(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $this->assertBoothOrder(
            $this->actingAs($responsibles[0])->get(route('circles.index', $event))->getContent()
        );
    }
}
