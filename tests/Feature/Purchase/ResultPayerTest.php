<?php

namespace Tests\Feature\Purchase;

use App\Enums\EventStatus;
use App\Services\PurchaseListService;
use App\Services\PurchaseResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 立替えた人（返金の受取先）が、責任者の訂正ですり替わらないことを確認する。
 */
class ResultPayerTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private function preparedItem(): array
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $responsible = $highests[0];
        $buyer = $members[0];
        $wanter = $members[1];

        $event = $this->makeEvent($group, $responsible, EventStatus::Accepting, [$responsible, $buyer, $wanter]);
        ['circle' => $circle, 'products' => $products] = $this->makeCatalog($event, '夏空スタジオ', [['name' => '新刊', 'price' => 1000]]);

        $purchases = app(PurchaseListService::class);
        $purchases->savePersonalPurchases($event, $wanter, [$products[0]->id => 1]);

        $shared = $purchases->syncSharedPurchaseFromWishes($circle->fresh(), $responsible);
        $purchases->assign($shared, $buyer, $responsible);

        $item = $shared->items()->firstOrFail();

        return compact('event', 'responsible', 'buyer', 'wanter', 'item');
    }

    public function test_responsible_editing_a_result_keeps_the_original_payer(): void
    {
        ['responsible' => $responsible, 'buyer' => $buyer, 'item' => $item] = $this->preparedItem();

        $results = app(PurchaseResultService::class);
        $result = $results->recordForSharedItem($item, $buyer, 1, 1000);
        $this->assertSame($buyer->id, $result->purchase_assignee_user_id);

        // 責任者が同じ内容で登録し直しても、立替者は買った本人のまま
        $result = $results->recordForSharedItem($item->fresh(), $responsible, 1, 1200);

        $this->assertSame($buyer->id, $result->purchase_assignee_user_id);
        $this->assertSame(1200, $result->unit_price);
    }

    public function test_responsible_recording_first_uses_the_confirmed_assignee(): void
    {
        ['responsible' => $responsible, 'buyer' => $buyer, 'item' => $item] = $this->preparedItem();

        // 担当者が登録する前に責任者が代理で登録した場合も、立替者は担当者
        $result = app(PurchaseResultService::class)->recordForSharedItem($item, $responsible, 1, 1000);

        $this->assertSame($buyer->id, $result->purchase_assignee_user_id);
    }

    public function test_settlement_still_points_at_the_real_payer_after_an_edit(): void
    {
        ['event' => $event, 'responsible' => $responsible, 'buyer' => $buyer, 'wanter' => $wanter, 'item' => $item] = $this->preparedItem();

        $results = app(PurchaseResultService::class);
        $results->recordForSharedItem($item, $buyer, 1, 1000);

        $events = app(\App\Services\EventService::class);
        $events->advance($event->fresh());  // 受付中 → 確定済
        $events->advance($event->fresh());  // 確定済 → 開催中

        // 責任者が結果を訂正する
        $this->actingAs($responsible)->post(route('results.store', $item), [
            'purchased_quantity' => 1,
            'unit_price' => 1000,
        ])->assertRedirect();

        $events->advance($event->fresh());  // 開催中 → 精算中

        $settlement = $event->fresh()->settlements()->firstOrFail();

        $this->assertSame($wanter->id, $settlement->payer_user_id);
        $this->assertSame($buyer->id, $settlement->payee_user_id, '立替えた本人ではなく責任者が受取先になっています');
    }
}
