<?php

namespace Tests\Feature\Purchase;

use App\Enums\EventStatus;
use App\Models\PersonalPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class PersonalPurchaseTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private function scenario(EventStatus $status = EventStatus::Accepting): array
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], $status, array_merge($responsibles, $members));
        ['circle' => $circle, 'products' => $products] = $this->makeCatalog($event, '夏空スタジオ', [
            ['name' => '新刊イラスト集', 'price' => 1500],
            ['name' => 'アクリルスタンド', 'price' => 800],
        ]);

        return compact('group', 'responsibles', 'members', 'event', 'circle', 'products');
    }

    public function test_participant_can_save_wishes(): void
    {
        ['event' => $event, 'members' => $members, 'products' => $products] = $this->scenario();

        $this->actingAs($members[0])
            ->patch(route('purchases.personal.update', $event), [
                'quantities' => [
                    $products[0]->id => 2,
                    $products[1]->id => 1,
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, PersonalPurchase::count());
        $this->assertSame(2, PersonalPurchase::where('event_product_id', $products[0]->id)->first()->planned_quantity);
    }

    public function test_zero_quantity_removes_the_wish(): void
    {
        ['event' => $event, 'members' => $members, 'products' => $products] = $this->scenario();

        $this->actingAs($members[0])->patch(route('purchases.personal.update', $event), [
            'quantities' => [$products[0]->id => 2],
        ]);
        $this->assertSame(1, PersonalPurchase::count());

        $this->actingAs($members[0])->patch(route('purchases.personal.update', $event), [
            'quantities' => [$products[0]->id => 0],
        ]);
        $this->assertSame(0, PersonalPurchase::count());
    }

    public function test_wishes_are_updated_not_duplicated(): void
    {
        ['event' => $event, 'members' => $members, 'products' => $products] = $this->scenario();

        $this->actingAs($members[0])->patch(route('purchases.personal.update', $event), [
            'quantities' => [$products[0]->id => 1],
        ]);
        $this->actingAs($members[0])->patch(route('purchases.personal.update', $event), [
            'quantities' => [$products[0]->id => 5],
        ]);

        $this->assertSame(1, PersonalPurchase::count());
        $this->assertSame(5, PersonalPurchase::first()->planned_quantity);
    }

    public function test_negative_quantity_is_rejected(): void
    {
        ['event' => $event, 'members' => $members, 'products' => $products] = $this->scenario();

        $this->actingAs($members[0])
            ->patch(route('purchases.personal.update', $event), [
                'quantities' => [$products[0]->id => -1],
            ])
            ->assertSessionHasErrors('quantities.'.$products[0]->id);
    }

    public function test_non_participant_cannot_save_wishes(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting);
        ['products' => $products] = $this->makeCatalog($event);
        $outsider = \App\Models\User::factory()->create();

        $this->actingAs($outsider)
            ->patch(route('purchases.personal.update', $event), ['quantities' => [$products[0]->id => 1]])
            ->assertForbidden();
    }

    public function test_wishes_are_locked_after_fixing(): void
    {
        ['event' => $event, 'members' => $members, 'products' => $products] = $this->scenario();
        $event->update(['status' => EventStatus::Fixed]);

        $this->actingAs($members[0])
            ->patch(route('purchases.personal.update', $event->fresh()), ['quantities' => [$products[0]->id => 1]])
            ->assertForbidden();
    }

    public function test_wishes_from_other_events_are_ignored(): void
    {
        ['group' => $group, 'responsibles' => $responsibles, 'members' => $members, 'event' => $event] = $this->scenario();
        $otherEvent = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $members);
        ['products' => $otherProducts] = $this->makeCatalog($otherEvent, 'よその会');

        $this->actingAs($members[0])->patch(route('purchases.personal.update', $event), [
            'quantities' => [$otherProducts[0]->id => 3],
        ]);

        $this->assertSame(0, PersonalPurchase::count());
    }

    public function test_screens_render(): void
    {
        ['event' => $event, 'members' => $members, 'responsibles' => $responsibles, 'products' => $products] = $this->scenario();
        $this->actingAs($members[0])->patch(route('purchases.personal.update', $event), [
            'quantities' => [$products[0]->id => 2],
        ]);

        $this->actingAs($members[0])
            ->get(route('purchases.personal.index', $event))
            ->assertOk()
            ->assertSee('新刊イラスト集')
            ->assertSee('¥3,000');

        $this->actingAs($responsibles[0])
            ->get(route('purchases.summary', $event))
            ->assertOk()
            ->assertSee('夏空スタジオ');
    }
}
