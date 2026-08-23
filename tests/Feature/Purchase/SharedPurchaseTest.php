<?php

namespace Tests\Feature\Purchase;

use App\Enums\EventStatus;
use App\Models\SharedPurchase;
use App\Models\SharedPurchaseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class SharedPurchaseTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    /**
     * 参加者2人が希望を登録した状態のイベントを作る。
     */
    private function scenario(): array
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = array_merge($highests, $responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);
        ['circle' => $circle, 'products' => $products] = $this->makeCatalog($event, '夏空スタジオ', [
            ['name' => '新刊', 'price' => 1000],
            ['name' => 'グッズ', 'price' => 500],
        ]);

        $this->actingAs($members[0])->patch(route('purchases.personal.update', $event), [
            'quantities' => [$products[0]->id => 2, $products[1]->id => 1],
        ]);
        $this->actingAs($members[1])->patch(route('purchases.personal.update', $event), [
            'quantities' => [$products[0]->id => 1],
        ]);

        return compact('group', 'highests', 'responsibles', 'members', 'event', 'circle', 'products');
    }

    public function test_responsible_can_sync_shared_purchase_from_wishes(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'products' => $products] = $this->scenario();

        $this->actingAs($responsibles[0])
            ->post(route('purchases.shared.sync', $event))
            ->assertRedirect();

        $sharedPurchase = SharedPurchase::first();
        $this->assertNotNull($sharedPurchase);
        $this->assertSame(2, $sharedPurchase->items()->count());
        $this->assertSame(3, $sharedPurchase->items()->where('event_product_id', $products[0]->id)->first()->planned_quantity);
        $this->assertSame(1, $sharedPurchase->items()->where('event_product_id', $products[1]->id)->first()->planned_quantity);
        $this->assertSame(3500, $sharedPurchase->fresh('items')->plannedAmount());
    }

    public function test_general_member_cannot_sync(): void
    {
        ['event' => $event, 'members' => $members] = $this->scenario();

        $this->actingAs($members[0])
            ->post(route('purchases.shared.sync', $event))
            ->assertForbidden();
    }

    public function test_resync_updates_quantities(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'members' => $members, 'products' => $products] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));

        $this->actingAs($members[1])->patch(route('purchases.personal.update', $event), [
            'quantities' => [$products[0]->id => 4],
        ]);
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));

        $item = SharedPurchaseItem::where('event_product_id', $products[0]->id)->first();
        $this->assertSame(6, $item->planned_quantity);
    }

    public function test_item_quantity_can_be_adjusted_manually(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'products' => $products] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $item = SharedPurchaseItem::where('event_product_id', $products[0]->id)->first();

        $this->actingAs($responsibles[0])
            ->patch(route('purchases.shared.items.update', $item), ['planned_quantity' => 10])
            ->assertRedirect();

        $this->assertSame(10, $item->fresh()->planned_quantity);
    }

    public function test_item_can_be_removed_with_zero_quantity(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'products' => $products] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $item = SharedPurchaseItem::where('event_product_id', $products[1]->id)->first();

        $this->actingAs($responsibles[0])
            ->patch(route('purchases.shared.items.update', $item), ['planned_quantity' => 0])
            ->assertRedirect();

        $this->assertSoftDeleted('shared_purchase_items', ['id' => $item->id]);
    }

    public function test_participant_can_volunteer_and_withdraw(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $sharedPurchase = SharedPurchase::first();

        $this->actingAs($members[0])
            ->post(route('purchases.assignees.volunteer', $sharedPurchase))
            ->assertRedirect();
        $this->assertSame(1, $sharedPurchase->assignees()->count());
        $this->assertFalse($sharedPurchase->hasConfirmedAssignee());

        $this->actingAs($members[0])
            ->delete(route('purchases.assignees.withdraw', $sharedPurchase))
            ->assertRedirect();
        $this->assertSame(0, $sharedPurchase->assignees()->count());
    }

    public function test_responsible_can_confirm_a_volunteer(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $sharedPurchase = SharedPurchase::first();
        $this->actingAs($members[0])->post(route('purchases.assignees.volunteer', $sharedPurchase));

        $this->actingAs($responsibles[0])
            ->post(route('purchases.assignees.assign', [$sharedPurchase, $members[0]]))
            ->assertRedirect();

        $this->assertTrue($sharedPurchase->fresh()->hasConfirmedAssignee());
    }

    public function test_confirmed_assignee_cannot_withdraw_by_themselves(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $sharedPurchase = SharedPurchase::first();
        $this->actingAs($responsibles[0])->post(route('purchases.assignees.assign', [$sharedPurchase, $members[0]]));

        $this->actingAs($members[0])
            ->delete(route('purchases.assignees.withdraw', $sharedPurchase))
            ->assertForbidden();
    }

    public function test_non_participant_cannot_be_assigned(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $sharedPurchase = SharedPurchase::first();
        $outsider = User::factory()->create();

        $this->actingAs($responsibles[0])
            ->post(route('purchases.assignees.assign', [$sharedPurchase, $outsider]))
            ->assertSessionHasErrors('assignee');
    }

    public function test_general_member_cannot_assign_others(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $sharedPurchase = SharedPurchase::first();

        $this->actingAs($members[0])
            ->post(route('purchases.assignees.assign', [$sharedPurchase, $members[1]]))
            ->assertForbidden();
    }

    public function test_event_cannot_be_fixed_without_confirmed_assignee(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));

        $this->actingAs($responsibles[0])
            ->post(route('events.advance', $event))
            ->assertSessionHasErrors('event');

        $this->assertSame(EventStatus::Accepting, $event->fresh()->status);
    }

    public function test_event_can_be_fixed_once_assignee_is_confirmed(): void
    {
        ['event' => $event, 'highests' => $highests, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $sharedPurchase = SharedPurchase::first();
        $this->actingAs($responsibles[0])->post(route('purchases.assignees.assign', [$sharedPurchase, $members[0]]));

        // 最高責任者が申請すると即時可決される
        $this->actingAs($highests[0])
            ->post(route('events.advance', $event))
            ->assertRedirect();

        $this->assertSame(EventStatus::Fixed, $event->fresh()->status);
    }

    public function test_volunteering_is_closed_after_fixing(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $sharedPurchase = SharedPurchase::first();
        $event->update(['status' => EventStatus::Fixed]);

        $this->actingAs($members[0])
            ->post(route('purchases.assignees.volunteer', $sharedPurchase->fresh()))
            ->assertForbidden();
    }

    public function test_responsible_can_still_assign_after_fixing(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $sharedPurchase = SharedPurchase::first();
        $event->update(['status' => EventStatus::Fixed]);

        $this->actingAs($responsibles[0])
            ->post(route('purchases.assignees.assign', [$sharedPurchase->fresh(), $members[0]]))
            ->assertRedirect();

        $this->assertTrue($sharedPurchase->fresh()->hasConfirmedAssignee());
    }

    public function test_screens_render(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $sharedPurchase = SharedPurchase::first();
        $this->actingAs($members[0])->post(route('purchases.assignees.volunteer', $sharedPurchase));

        $this->actingAs($responsibles[0])
            ->get(route('purchases.shared.index', $event))
            ->assertOk()
            ->assertSee('夏空スタジオ')
            ->assertSee('¥3,500');

        $this->actingAs($responsibles[0])
            ->get(route('purchases.shared.show', $sharedPurchase))
            ->assertOk()
            ->assertSee('立候補中');
    }
}
