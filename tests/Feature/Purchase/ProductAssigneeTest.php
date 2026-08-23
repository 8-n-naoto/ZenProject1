<?php

namespace Tests\Feature\Purchase;

use App\Enums\EventStatus;
use App\Models\ProductPurchaseAssignee;
use App\Models\PurchaseResult;
use App\Models\SharedPurchase;
use App\Models\SharedPurchaseItem;
use App\Models\User;
use App\Services\PurchaseListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 1つのサークルを複数人で分担する（商品単位の担当）機能。
 */
class ProductAssigneeTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    /**
     * 参加者3人・商品1つ（希望6点）で、buyer がサークル担当のシナリオ。
     */
    private function scenario(): array
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 3]);
        $participants = array_merge($responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);
        ['products' => $products] = $this->makeCatalog($event, '夏空スタジオ', [['name' => '新刊', 'price' => 1000]]);

        $purchases = app(PurchaseListService::class);
        $purchases->savePersonalPurchases($event, $members[2], [$products[0]->id => 6]);
        $purchases->syncAll($event, $responsibles[0]);

        $sharedPurchase = SharedPurchase::first();
        $purchases->assign($sharedPurchase, $members[0], $responsibles[0]);

        return [
            'group' => $group,
            'responsibles' => $responsibles,
            'members' => $members,
            'event' => $event->fresh(),
            'item' => SharedPurchaseItem::first(),
            'sharedPurchase' => $sharedPurchase->fresh(),
        ];
    }

    public function test_responsible_can_split_an_item_between_participants(): void
    {
        ['item' => $item, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();

        $this->actingAs($responsibles[0])
            ->patch(route('purchases.shared.items.assignees', $item), [
                'assignees' => [$members[0]->id => 4, $members[1]->id => 2],
            ])
            ->assertRedirect();

        $this->assertSame(2, ProductPurchaseAssignee::count());
        $this->assertSame(4, ProductPurchaseAssignee::where('user_id', $members[0]->id)->first()->assigned_quantity);
    }

    public function test_total_cannot_exceed_the_planned_quantity(): void
    {
        ['item' => $item, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();

        $this->actingAs($responsibles[0])
            ->patch(route('purchases.shared.items.assignees', $item), [
                'assignees' => [$members[0]->id => 5, $members[1]->id => 5],
            ])
            ->assertSessionHasErrors('assignees');

        $this->assertSame(0, ProductPurchaseAssignee::count());
    }

    public function test_non_participants_cannot_be_assigned(): void
    {
        ['item' => $item, 'responsibles' => $responsibles] = $this->scenario();
        $outsider = User::factory()->create();

        $this->actingAs($responsibles[0])
            ->patch(route('purchases.shared.items.assignees', $item), [
                'assignees' => [$outsider->id => 1],
            ])
            ->assertSessionHasErrors('assignees');
    }

    public function test_circle_assignee_can_split_their_own_circle(): void
    {
        ['item' => $item, 'members' => $members] = $this->scenario();

        $this->actingAs($members[0])
            ->patch(route('purchases.shared.items.assignees', $item), [
                'assignees' => [$members[1]->id => 3],
            ])
            ->assertRedirect();

        $this->assertSame(1, ProductPurchaseAssignee::count());
    }

    public function test_unrelated_member_cannot_split(): void
    {
        ['item' => $item, 'members' => $members] = $this->scenario();

        $this->actingAs($members[2])
            ->patch(route('purchases.shared.items.assignees', $item), [
                'assignees' => [$members[1]->id => 1],
            ])
            ->assertForbidden();
    }

    public function test_assignment_is_replaced_not_appended(): void
    {
        ['item' => $item, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();

        $this->actingAs($responsibles[0])->patch(route('purchases.shared.items.assignees', $item), [
            'assignees' => [$members[0]->id => 4, $members[1]->id => 2],
        ]);
        $this->actingAs($responsibles[0])->patch(route('purchases.shared.items.assignees', $item), [
            'assignees' => [$members[1]->id => 1],
        ]);

        $this->assertSame(1, ProductPurchaseAssignee::count());
        $this->assertSame(1, ProductPurchaseAssignee::first()->assigned_quantity);
    }

    public function test_product_assignee_sees_the_item_in_their_shopping_list(): void
    {
        ['item' => $item, 'event' => $event, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();

        $this->actingAs($responsibles[0])->patch(route('purchases.shared.items.assignees', $item), [
            'assignees' => [$members[1]->id => 2],
        ]);

        $event->update(['status' => EventStatus::Ongoing, 'fixed_at' => now()]);

        $this->actingAs($members[1])
            ->get(route('shopping.index', $event->fresh()))
            ->assertOk()
            ->assertSee('夏空スタジオ')
            ->assertSee('あなたの担当 2点');
    }

    public function test_product_assignee_can_record_the_result(): void
    {
        ['item' => $item, 'event' => $event, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();

        $this->actingAs($responsibles[0])->patch(route('purchases.shared.items.assignees', $item), [
            'assignees' => [$members[1]->id => 2],
        ]);

        $event->update(['status' => EventStatus::Ongoing, 'fixed_at' => now()]);

        $this->actingAs($members[1])
            ->post(route('shopping.items.planned', $item->fresh()))
            ->assertRedirect();

        $this->assertSame(6, PurchaseResult::first()->purchased_quantity);
    }

    public function test_split_is_shown_on_the_shared_purchase_screen(): void
    {
        ['item' => $item, 'sharedPurchase' => $sharedPurchase, 'responsibles' => $responsibles, 'members' => $members] = $this->scenario();

        $this->actingAs($responsibles[0])->patch(route('purchases.shared.items.assignees', $item), [
            'assignees' => [$members[1]->id => 2],
        ]);

        $this->actingAs($responsibles[0])
            ->get(route('purchases.shared.show', $sharedPurchase))
            ->assertOk()
            ->assertSee('担当:')
            ->assertSee('2点');
    }
}
