<?php

namespace Tests\Feature\Event;

use App\Enums\EventStatus;
use App\Models\Circle;
use App\Models\Event;
use App\Models\EventCircle;
use App\Services\CatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class EventDuplicationTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'コミックマーケット106',
            'venue_name' => '東京ビッグサイト',
            'days' => [
                ['event_date' => now()->addDays(90)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '16:00'],
            ],
        ], $overrides);
    }

    public function test_circles_and_products_are_copied(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $source = $this->makeEvent($group, $responsibles[0], EventStatus::Completed, $members);

        $catalog = app(CatalogService::class);
        $circle = $catalog->createCircle($source, ['display_name' => '夏空スタジオ', 'booth' => '東1 ア-12a']);
        $catalog->createProduct($circle, ['name' => '新刊', 'price' => 1200]);
        $catalog->createProduct($circle, ['name' => 'グッズ', 'price' => 500]);

        $this->actingAs($responsibles[0])
            ->post(route('events.duplicate', $source), $this->payload())
            ->assertRedirect();

        $created = Event::firstWhere('name', 'コミックマーケット106');

        $this->assertNotNull($created);
        $this->assertSame(EventStatus::Preparation, $created->status);
        $this->assertSame(1, $created->eventCircles()->count());
        $this->assertSame(2, $created->eventProducts()->count());

        $copiedCircle = $created->eventCircles()->first();
        $this->assertSame('夏空スタジオ', $copiedCircle->display_name);
        $this->assertSame('東1 ア-12a', $copiedCircle->booth);

        // マスタは別レコードとして作られる（元イベントに影響しない）
        $this->assertSame(2, Circle::count());
        $this->assertSame(4, \App\Models\Product::count());
        $this->assertNotSame($circle->circle_id, $copiedCircle->circle_id);
    }

    public function test_purchases_and_assignees_are_not_copied(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $participants = array_merge($responsibles, $members);
        $source = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);
        ['products' => $products] = $this->makeCatalog($source);

        app(\App\Services\PurchaseListService::class)->savePersonalPurchases($source, $members[0], [$products[0]->id => 3]);
        app(\App\Services\PurchaseListService::class)->syncAll($source, $responsibles[0]);

        $this->actingAs($responsibles[0])->post(route('events.duplicate', $source), $this->payload());
        $created = Event::firstWhere('name', 'コミックマーケット106');

        $this->assertSame(0, $created->personalPurchases()->count());
        $this->assertSame(0, $created->sharedPurchases()->count());
        $this->assertSame(0, $created->participants()->count());
    }

    public function test_images_are_copied_as_separate_files(): void
    {
        Storage::fake('public');
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $source = $this->makeEvent($group, $responsibles[0]);

        $this->actingAs($responsibles[0])->post(route('circles.store', $source), [
            'display_name' => '夏空スタジオ',
            'map_image' => UploadedFile::fake()->image('map.png'),
        ]);

        $original = EventCircle::first();

        $this->actingAs($responsibles[0])->post(route('events.duplicate', $source), $this->payload());

        $copied = EventCircle::orderByDesc('id')->first();

        $this->assertNotSame($original->map_image_path, $copied->map_image_path);
        Storage::disk('public')->assertExists($original->map_image_path);
        Storage::disk('public')->assertExists($copied->map_image_path);
    }

    public function test_general_member_cannot_duplicate(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $source = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $members);

        $this->actingAs($members[0])
            ->post(route('events.duplicate', $source), $this->payload())
            ->assertForbidden();
    }

    public function test_duplicate_form_renders(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $source = $this->makeEvent($group, $responsibles[0]);
        $this->makeCatalog($source);

        $this->actingAs($responsibles[0])
            ->get(route('events.duplicate.form', $source))
            ->assertOk()
            ->assertSee('引き継いで');
    }
}
