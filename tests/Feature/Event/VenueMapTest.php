<?php

namespace Tests\Feature\Event;

use App\Enums\EventStatus;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\PurchaseListService;
use App\Services\PurchaseResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class VenueMapTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private function image(): UploadedFile
    {
        Storage::fake('public');

        return UploadedFile::fake()->image('venue.png', 1200, 800);
    }

    public function test_the_map_screen_explains_itself_when_no_map_is_registered(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting, [$highests[0]]);

        $this->actingAs($highests[0])->get(route('events.map', $event))
            ->assertOk()
            ->assertSee('会場図がまだ登録されていません')
            ->assertSee('転載が許可されているものか確認');
    }

    public function test_a_responsible_can_register_a_map_and_place_circles(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting, [$highests[0]]);
        ['circle' => $circle] = $this->makeCatalog($event, '夏空スタジオ', [['name' => '新刊', 'price' => 1000]]);

        $this->actingAs($highests[0])
            ->post(route('events.map.image', $event), ['map_image' => $this->image()])
            ->assertRedirect();

        $this->assertNotNull($event->fresh()->mapImageUrl());

        $this->actingAs($highests[0])
            ->patch(route('events.map.place', [$event, $circle]), ['venue_map_x' => 40, 'venue_map_y' => 65])
            ->assertRedirect();

        $this->assertSame(['x' => 40, 'y' => 65], $circle->fresh()->venueMapPin());

        $this->actingAs($highests[0])->get(route('events.map', $event->fresh()))
            ->assertOk()
            ->assertSee('left: 40%; top: 65%', false)
            ->assertSee('夏空スタジオ');
    }

    public function test_bought_and_unbought_circles_are_coloured_differently(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $buyer = $highests[0];
        $wanter = $members[0];
        $event = $this->makeEvent($group, $buyer, EventStatus::Accepting, [$buyer, $wanter]);

        $catalog = app(CatalogService::class);
        $purchases = app(PurchaseListService::class);

        $done = $catalog->createCircle($event, ['display_name' => '購入済サークル', 'booth' => '東1 ア-01a']);
        $doneProduct = $catalog->createProduct($done, ['name' => '新刊A', 'price' => 1000]);

        $pending = $catalog->createCircle($event, ['display_name' => '未購入サークル', 'booth' => '東2 ウ-02a']);
        $pendingProduct = $catalog->createProduct($pending, ['name' => '新刊B', 'price' => 1000]);

        foreach ([$doneProduct, $pendingProduct] as $product) {
            $purchases->savePersonalPurchases($event, $wanter, [$product->id => 1]);
        }

        foreach ([$done, $pending] as $circle) {
            $shared = $purchases->syncSharedPurchaseFromWishes($circle->fresh(), $buyer);
            $purchases->assign($shared, $buyer, $buyer);
        }

        $event->update(['status' => EventStatus::Ongoing, 'fixed_at' => now()]);

        // 片方だけ購入結果を登録する
        $item = $done->fresh()->sharedPurchase->items()->firstOrFail();
        app(PurchaseResultService::class)->recordForSharedItem($item, $buyer, 1, 1000);

        $done->update(['venue_map_x' => 10, 'venue_map_y' => 10]);
        $pending->update(['venue_map_x' => 80, 'venue_map_y' => 80]);

        $event->update(['map_image_path' => 'venue-maps/test.png']);

        $html = $this->actingAs($buyer)->get(route('events.map', $event->fresh()))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('bg-emerald-500', $html, '購入済のピンが緑になっていません');
        $this->assertStringContainsString('bg-rose-500', $html, '未購入のピンが赤になっていません');
        $this->assertStringContainsString('購入済', $html);
    }

    public function test_replacing_the_map_clears_the_placed_positions(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting, [$highests[0]]);
        ['circle' => $circle] = $this->makeCatalog($event, '夏空スタジオ', [['name' => '新刊', 'price' => 1000]]);

        $this->actingAs($highests[0])->post(route('events.map.image', $event), ['map_image' => $this->image()]);
        $this->actingAs($highests[0])->patch(route('events.map.place', [$event, $circle]), ['venue_map_x' => 20, 'venue_map_y' => 30]);
        $this->assertNotNull($circle->fresh()->venueMapPin());

        // 会場図を差し替えると、前の図に対する座標は意味を失うので消える
        $this->actingAs($highests[0])
            ->post(route('events.map.image', $event), ['map_image' => UploadedFile::fake()->image('venue2.png', 900, 600)])
            ->assertRedirect();

        $this->assertNull($circle->fresh()->venueMapPin());
    }

    public function test_a_general_member_can_view_but_not_edit(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting, [$highests[0], $members[0]]);
        ['circle' => $circle] = $this->makeCatalog($event, '夏空スタジオ', [['name' => '新刊', 'price' => 1000]]);

        $this->actingAs($members[0])->get(route('events.map', $event))
            ->assertOk()
            ->assertDontSee('会場図の登録');

        $this->actingAs($members[0])
            ->post(route('events.map.image', $event), ['map_image' => $this->image()])
            ->assertForbidden();

        $this->actingAs($members[0])
            ->patch(route('events.map.place', [$event, $circle]), ['venue_map_x' => 10, 'venue_map_y' => 10])
            ->assertForbidden();
    }

    public function test_an_outsider_cannot_open_the_map(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting, [$highests[0]]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('events.map', $event))->assertForbidden();
    }

    public function test_a_circle_from_another_event_cannot_be_placed(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting, [$highests[0]]);
        $other = $this->makeEvent($group, $highests[0], EventStatus::Accepting, [$highests[0]]);
        ['circle' => $foreignCircle] = $this->makeCatalog($other, 'よその会', [['name' => '新刊', 'price' => 1000]]);

        $this->actingAs($highests[0])
            ->patch(route('events.map.place', [$event, $foreignCircle]), ['venue_map_x' => 10, 'venue_map_y' => 10])
            ->assertNotFound();
    }

    public function test_out_of_range_positions_are_rejected(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting, [$highests[0]]);
        ['circle' => $circle] = $this->makeCatalog($event, '夏空スタジオ', [['name' => '新刊', 'price' => 1000]]);

        $this->actingAs($highests[0])
            ->patch(route('events.map.place', [$event, $circle]), ['venue_map_x' => 150, 'venue_map_y' => 10])
            ->assertSessionHasErrors('venue_map_x');
    }

    public function test_unplaced_circles_are_listed(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting, [$highests[0]]);
        $this->makeCatalog($event, 'まだ置いていない会', [['name' => '新刊', 'price' => 1000]]);

        $this->actingAs($highests[0])->get(route('events.map', $event))
            ->assertOk()
            ->assertSee('まだ会場図に置いていないサークル（1件）')
            ->assertSee('まだ置いていない会');
    }
}
