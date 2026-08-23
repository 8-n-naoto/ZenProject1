<?php

namespace Tests\Feature\Purchase;

use App\Enums\EventStatus;
use App\Models\PersonalPurchase;
use App\Services\PurchaseListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class CopyWishesTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_wishes_are_copied_by_circle_and_product_name(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $user = $highests[0];

        $past = $this->makeEvent($group, $user, EventStatus::Accepting, [$user]);
        ['products' => $pastProducts] = $this->makeCatalog($past, '夏空スタジオ', [
            ['name' => '新刊', 'price' => 1000],
            ['name' => '既刊', 'price' => 500],
        ]);

        $purchases = app(PurchaseListService::class);
        $purchases->savePersonalPurchases($past, $user, [
            $pastProducts[0]->id => 2,
            $pastProducts[1]->id => 1,
        ]);

        $next = $this->makeEvent($group, $user, EventStatus::Accepting, [$user]);
        // 表記ゆれ（全角・空白）があっても一致させる
        ['products' => $nextProducts] = $this->makeCatalog($next, '夏空 スタジオ', [
            ['name' => '新刊', 'price' => 1200],
        ]);

        $result = $purchases->copyWishesFrom($next->fresh(), $past->fresh(), $user);

        $this->assertSame(1, $result['copied']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(1, $result['missing']);

        $this->assertDatabaseHas('personal_purchases', [
            'event_id' => $next->id,
            'event_product_id' => $nextProducts[0]->id,
            'user_id' => $user->id,
            'planned_quantity' => 2,
        ]);
    }

    public function test_existing_wishes_are_not_overwritten(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $user = $highests[0];
        $purchases = app(PurchaseListService::class);

        $past = $this->makeEvent($group, $user, EventStatus::Accepting, [$user]);
        ['products' => $pastProducts] = $this->makeCatalog($past, '夏空スタジオ', [['name' => '新刊', 'price' => 1000]]);
        $purchases->savePersonalPurchases($past, $user, [$pastProducts[0]->id => 5]);

        $next = $this->makeEvent($group, $user, EventStatus::Accepting, [$user]);
        ['products' => $nextProducts] = $this->makeCatalog($next, '夏空スタジオ', [['name' => '新刊', 'price' => 1000]]);
        $purchases->savePersonalPurchases($next, $user, [$nextProducts[0]->id => 1]);

        $result = $purchases->copyWishesFrom($next->fresh(), $past->fresh(), $user);

        $this->assertSame(0, $result['copied']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, PersonalPurchase::where('event_id', $next->id)->first()->planned_quantity);
    }

    public function test_other_groups_event_is_rejected(): void
    {
        ['group' => $groupA, 'highest' => $highestsA] = $this->makeGroup();
        ['group' => $groupB] = $this->makeGroup();
        $user = $highestsA[0];
        $groupB->members()->attach($user->id, ['role' => \App\Enums\GroupRole::Member->value, 'joined_at' => now()]);

        $past = $this->makeEvent($groupB, $user, EventStatus::Accepting, [$user]);
        $next = $this->makeEvent($groupA, $user, EventStatus::Accepting, [$user]);

        $this->expectException(\App\Exceptions\BusinessRuleException::class);
        app(PurchaseListService::class)->copyWishesFrom($next, $past, $user);
    }

    public function test_same_event_is_rejected(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting, [$highests[0]]);

        $this->expectException(\App\Exceptions\BusinessRuleException::class);
        app(PurchaseListService::class)->copyWishesFrom($event, $event, $highests[0]);
    }

    public function test_copy_endpoint_reports_the_outcome(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $user = $highests[0];
        $purchases = app(PurchaseListService::class);

        $past = $this->makeEvent($group, $user, EventStatus::Accepting, [$user]);
        ['products' => $pastProducts] = $this->makeCatalog($past, '夏空スタジオ', [['name' => '新刊', 'price' => 1000]]);
        $purchases->savePersonalPurchases($past, $user, [$pastProducts[0]->id => 3]);

        $next = $this->makeEvent($group, $user, EventStatus::Accepting, [$user]);
        $this->makeCatalog($next, '夏空スタジオ', [['name' => '新刊', 'price' => 1000]]);

        $this->actingAs($user)
            ->post(route('purchases.personal.copy', $next), ['source_event_id' => $past->id])
            ->assertRedirect(route('purchases.personal.index', $next))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('personal_purchases', [
            'event_id' => $next->id,
            'user_id' => $user->id,
            'planned_quantity' => 3,
        ]);
    }

    public function test_copy_endpoint_rejects_an_event_from_another_group(): void
    {
        ['group' => $groupA, 'highest' => $highestsA] = $this->makeGroup();
        ['group' => $groupB, 'highest' => $highestsB] = $this->makeGroup();
        $user = $highestsA[0];

        $foreign = $this->makeEvent($groupB, $highestsB[0], EventStatus::Accepting, [$highestsB[0]]);
        $next = $this->makeEvent($groupA, $user, EventStatus::Accepting, [$user]);

        $this->actingAs($user)
            ->post(route('purchases.personal.copy', $next), ['source_event_id' => $foreign->id])
            ->assertSessionHasErrors('source_event_id');
    }

    public function test_copyable_sources_only_list_events_with_my_wishes(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $user = $highests[0];
        $purchases = app(PurchaseListService::class);

        $withWishes = $this->makeEvent($group, $user, EventStatus::Accepting, [$user]);
        ['products' => $products] = $this->makeCatalog($withWishes, '夏空スタジオ', [['name' => '新刊', 'price' => 1000]]);
        $purchases->savePersonalPurchases($withWishes, $user, [$products[0]->id => 1]);

        $othersWishes = $this->makeEvent($group, $user, EventStatus::Accepting, [$members[0]]);
        ['products' => $otherProducts] = $this->makeCatalog($othersWishes, '冬空スタジオ', [['name' => '新刊', 'price' => 1000]]);
        $purchases->savePersonalPurchases($othersWishes, $members[0], [$otherProducts[0]->id => 1]);

        $target = $this->makeEvent($group, $user, EventStatus::Accepting, [$user]);

        $sources = $purchases->copyableSourceEvents($target, $user);

        $this->assertSame([$withWishes->id], $sources->pluck('id')->all());
    }
}
