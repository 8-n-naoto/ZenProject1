<?php

namespace Tests\Feature\Catalog;

use App\Enums\EventStatus;
use App\Services\CatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class MapPinTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private function image(): UploadedFile
    {
        Storage::fake('public');

        return UploadedFile::fake()->image('map.png', 400, 300);
    }

    public function test_pin_is_saved_with_the_map_image(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation);

        $circle = app(CatalogService::class)->createCircle($event, [
            'display_name' => '夏空スタジオ',
            'booth' => '東1 ア-12a',
            'map_image' => $this->image(),
            'map_x' => 40,
            'map_y' => 65,
        ]);

        $this->assertSame(['x' => 40, 'y' => 65], $circle->fresh()->mapPin());
    }

    public function test_pin_is_ignored_without_a_map_image(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation);

        $circle = app(CatalogService::class)->createCircle($event, [
            'display_name' => '夏空スタジオ',
            'map_x' => 40,
            'map_y' => 65,
        ]);

        $this->assertNull($circle->fresh()->mapPin());
    }

    public function test_pin_can_be_moved_and_cleared(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation);
        $catalog = app(CatalogService::class);

        $circle = $catalog->createCircle($event, [
            'display_name' => '夏空スタジオ',
            'map_image' => $this->image(),
            'map_x' => 10,
            'map_y' => 10,
        ]);

        $circle = $catalog->updateCircle($circle, [
            'display_name' => '夏空スタジオ',
            'map_x' => 80,
            'map_y' => 20,
        ]);
        $this->assertSame(['x' => 80, 'y' => 20], $circle->mapPin());

        $circle = $catalog->updateCircle($circle, [
            'display_name' => '夏空スタジオ',
            'map_x' => null,
            'map_y' => null,
        ]);
        $this->assertNull($circle->mapPin());
    }

    public function test_uploading_a_new_image_clears_the_pin(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation);
        $catalog = app(CatalogService::class);

        $circle = $catalog->createCircle($event, [
            'display_name' => '夏空スタジオ',
            'map_image' => $this->image(),
            'map_x' => 10,
            'map_y' => 80,
        ]);

        // 画面から古いピンの値が送られてきても、画像を差し替えたら無視して消す
        $circle = $catalog->updateCircle($circle, [
            'display_name' => '夏空スタジオ',
            'map_image' => UploadedFile::fake()->image('map2.png', 500, 500),
            'map_x' => 10,
            'map_y' => 80,
        ]);

        $this->assertNull($circle->map_x);
        $this->assertNull($circle->map_y);
        $this->assertNull($circle->mapPin());
    }

    public function test_replacing_the_image_clears_the_pin(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation);
        $catalog = app(CatalogService::class);

        $circle = $catalog->createCircle($event, [
            'display_name' => '夏空スタジオ',
            'map_image' => $this->image(),
            'map_x' => 10,
            'map_y' => 10,
        ]);

        $circle = $catalog->updateCircle($circle, [
            'display_name' => '夏空スタジオ',
            'remove_map_image' => true,
        ]);

        $this->assertNull($circle->map_x);
        $this->assertNull($circle->map_y);
        $this->assertNull($circle->mapPin());
    }

    public function test_pin_is_posted_from_the_edit_screen(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation);

        $circle = app(CatalogService::class)->createCircle($event, [
            'display_name' => '夏空スタジオ',
            'map_image' => $this->image(),
        ]);

        $this->actingAs($highests[0])
            ->patch(route('circles.update', $circle), [
                'display_name' => '夏空スタジオ',
                'map_x' => 25,
                'map_y' => 75,
            ])
            ->assertRedirect();

        $this->assertSame(['x' => 25, 'y' => 75], $circle->fresh()->mapPin());

        $this->actingAs($highests[0])
            ->get(route('circles.show', $circle))
            ->assertOk()
            ->assertSee('left: 25%; top: 75%', false);
    }

    public function test_out_of_range_pin_is_rejected(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation);

        $circle = app(CatalogService::class)->createCircle($event, [
            'display_name' => '夏空スタジオ',
            'map_image' => $this->image(),
        ]);

        $this->actingAs($highests[0])
            ->patch(route('circles.update', $circle), [
                'display_name' => '夏空スタジオ',
                'map_x' => 140,
                'map_y' => 10,
            ])
            ->assertSessionHasErrors('map_x');
    }
}
