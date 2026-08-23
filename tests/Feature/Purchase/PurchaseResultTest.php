<?php

namespace Tests\Feature\Purchase;

use App\Enums\EventStatus;
use App\Enums\PurchaseResultStatus;
use App\Models\PurchaseResult;
use App\Models\SharedPurchase;
use App\Models\SharedPurchaseItem;
use App\Services\PurchaseResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class PurchaseResultTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    /**
     * 参加者2人が「新刊」を 2点 / 1点 希望し、責任者が担当に確定した状態を作る。
     */
    private function scenario(EventStatus $status = EventStatus::Ongoing): array
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = array_merge($responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);
        ['circle' => $circle, 'products' => $products] = $this->makeCatalog($event, '夏空スタジオ', [
            ['name' => '新刊', 'price' => 1000],
        ]);

        $this->actingAs($members[0])->patch(route('purchases.personal.update', $event), [
            'quantities' => [$products[0]->id => 2],
        ]);
        $this->actingAs($members[1])->patch(route('purchases.personal.update', $event), [
            'quantities' => [$products[0]->id => 1],
        ]);

        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $sharedPurchase = SharedPurchase::first();
        $this->actingAs($responsibles[0])->post(route('purchases.assignees.assign', [$sharedPurchase, $members[0]]));

        $event->update(['status' => $status, 'fixed_at' => now()]);

        return [
            'group' => $group,
            'responsibles' => $responsibles,
            'members' => $members,
            'event' => $event->fresh(),
            'products' => $products,
            'sharedPurchase' => $sharedPurchase->fresh(),
            'item' => SharedPurchaseItem::first(),
        ];
    }

    public function test_assignee_can_record_exact_purchase(): void
    {
        ['members' => $members, 'item' => $item] = $this->scenario();

        $this->actingAs($members[0])
            ->post(route('results.store', $item), ['purchased_quantity' => 3])
            ->assertRedirect();

        $result = PurchaseResult::first();

        $this->assertSame(3, $result->purchased_quantity);
        $this->assertSame(3, $result->planned_quantity);
        $this->assertSame(PurchaseResultStatus::Completed, $result->status);
        $this->assertSame($members[0]->id, $result->purchase_assignee_user_id);
        $this->assertSame(0, $result->shortageUsers()->count());
        $this->assertSame(3000, $result->totalAmount());
    }

    public function test_shortage_must_be_allocated(): void
    {
        ['members' => $members, 'item' => $item] = $this->scenario();

        $this->actingAs($members[0])
            ->post(route('results.store', $item), ['purchased_quantity' => 1])
            ->assertSessionHasErrors('shortages');

        $this->assertSame(0, PurchaseResult::count());
    }

    public function test_shortage_allocation_is_recorded(): void
    {
        ['members' => $members, 'item' => $item] = $this->scenario();

        $this->actingAs($members[0])
            ->post(route('results.store', $item), [
                'purchased_quantity' => 1,
                'shortages' => [$members[0]->id => 1, $members[1]->id => 1],
            ])
            ->assertRedirect();

        $result = PurchaseResult::first();

        $this->assertSame(PurchaseResultStatus::Shortage, $result->status);
        $this->assertSame(2, $result->shortageUsers()->count());
        $this->assertSame(1, $result->shortageUsers()->where('user_id', $members[1]->id)->first()->shortage_quantity);
    }

    public function test_shortage_cannot_exceed_the_wish(): void
    {
        ['members' => $members, 'item' => $item] = $this->scenario();

        $this->actingAs($members[0])
            ->post(route('results.store', $item), [
                'purchased_quantity' => 1,
                'shortages' => [$members[1]->id => 2],
            ])
            ->assertSessionHasErrors('shortages');
    }

    public function test_shortage_cannot_be_assigned_to_a_non_wisher(): void
    {
        ['members' => $members, 'responsibles' => $responsibles, 'item' => $item] = $this->scenario();

        $this->actingAs($members[0])
            ->post(route('results.store', $item), [
                'purchased_quantity' => 2,
                'shortages' => [$responsibles[0]->id => 1],
            ])
            ->assertSessionHasErrors('shortages');
    }

    public function test_excess_requires_a_takeover_user(): void
    {
        ['members' => $members, 'item' => $item] = $this->scenario();

        $this->actingAs($members[0])
            ->post(route('results.store', $item), ['purchased_quantity' => 5])
            ->assertSessionHasErrors('excess_user_id');
    }

    public function test_excess_is_recorded(): void
    {
        ['members' => $members, 'item' => $item] = $this->scenario();

        $this->actingAs($members[0])
            ->post(route('results.store', $item), [
                'purchased_quantity' => 5,
                'excess_user_id' => $members[1]->id,
            ])
            ->assertRedirect();

        $result = PurchaseResult::first();

        $this->assertSame(PurchaseResultStatus::Excess, $result->status);
        $this->assertSame(2, $result->excessTakeover->takeover_quantity);
        $this->assertSame($members[1]->id, $result->excessTakeover->user_id);
    }

    public function test_result_can_be_corrected(): void
    {
        ['members' => $members, 'item' => $item] = $this->scenario();

        $this->actingAs($members[0])->post(route('results.store', $item), [
            'purchased_quantity' => 1,
            'shortages' => [$members[0]->id => 2],
        ]);
        $this->actingAs($members[0])->post(route('results.store', $item), ['purchased_quantity' => 3]);

        $this->assertSame(1, PurchaseResult::count());
        $result = PurchaseResult::first();
        $this->assertSame(3, $result->purchased_quantity);
        $this->assertSame(0, $result->shortageUsers()->count());
    }

    public function test_unit_price_override_is_used(): void
    {
        ['members' => $members, 'item' => $item] = $this->scenario();

        $this->actingAs($members[0])->post(route('results.store', $item), [
            'purchased_quantity' => 3,
            'unit_price' => 1200,
        ]);

        $result = PurchaseResult::first();
        $this->assertSame(1200, $result->effectiveUnitPrice());
        $this->assertSame(3600, $result->totalAmount());
    }

    public function test_non_assignee_cannot_record(): void
    {
        ['members' => $members, 'item' => $item] = $this->scenario();

        $this->actingAs($members[1])
            ->post(route('results.store', $item), ['purchased_quantity' => 3])
            ->assertForbidden();
    }

    public function test_responsible_can_record_on_behalf(): void
    {
        ['responsibles' => $responsibles, 'item' => $item] = $this->scenario();

        $this->actingAs($responsibles[0])
            ->post(route('results.store', $item), ['purchased_quantity' => 3])
            ->assertRedirect();

        $this->assertSame(1, PurchaseResult::count());
    }

    public function test_results_cannot_be_recorded_before_fixing(): void
    {
        ['members' => $members, 'item' => $item, 'event' => $event] = $this->scenario();
        $event->update(['status' => EventStatus::Accepting]);

        $this->actingAs($members[0])
            ->post(route('results.store', $item->fresh()), ['purchased_quantity' => 3])
            ->assertForbidden();
    }

    public function test_results_cannot_be_recorded_after_completion(): void
    {
        ['members' => $members, 'item' => $item, 'event' => $event] = $this->scenario();
        $event->update(['status' => EventStatus::Completed]);

        $this->actingAs($members[0])
            ->post(route('results.store', $item->fresh()), ['purchased_quantity' => 3])
            ->assertForbidden();
    }

    public function test_allocation_reflects_shortage_and_excess(): void
    {
        ['members' => $members, 'item' => $item] = $this->scenario();
        $service = app(PurchaseResultService::class);

        $this->actingAs($members[0])->post(route('results.store', $item), [
            'purchased_quantity' => 2,
            'shortages' => [$members[0]->id => 1],
        ]);

        $allocation = $service->allocationFor(PurchaseResult::first());

        $this->assertSame(1, $allocation[$members[0]->id]);
        $this->assertSame(1, $allocation[$members[1]->id]);
    }

    public function test_shortage_suggestion_is_fair(): void
    {
        ['item' => $item, 'members' => $members] = $this->scenario();

        $suggestion = app(PurchaseResultService::class)->suggestShortageAllocation($item, 1);

        // 希望 2点/1点 に対して 1点しか買えない（不足2点）。
        // 希望が多い人から1点ずつ削るため、2点希望の人が2点不足し、1点希望の人は受け取れる。
        $this->assertSame(2, array_sum($suggestion));
        $this->assertSame(2, $suggestion[$members[0]->id]);
        $this->assertArrayNotHasKey($members[1]->id, $suggestion);
    }

    public function test_screens_render(): void
    {
        ['members' => $members, 'item' => $item, 'event' => $event] = $this->scenario();

        $this->actingAs($members[0])
            ->get(route('results.index', $event))
            ->assertOk()
            ->assertSee('夏空スタジオ');

        $this->actingAs($members[0])
            ->get(route('results.edit', $item))
            ->assertOk()
            ->assertSee('新刊');
    }
}
