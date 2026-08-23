<?php

namespace Tests\Feature\Catalog;

use App\Models\EventCircle;
use App\Models\EventProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_circle_map_image_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);

        $this->actingAs($responsibles[0])->post(route('circles.store', $event), [
            'display_name' => '夏空スタジオ',
            'map_image' => UploadedFile::fake()->image('map.png', 400, 300),
        ])->assertRedirect();

        $circle = EventCircle::first();
        $this->assertNotNull($circle->map_image_path);
        Storage::disk('public')->assertExists($circle->map_image_path);

        $path = $circle->map_image_path;

        $this->actingAs($responsibles[0])->patch(route('circles.update', $circle), [
            'display_name' => '夏空スタジオ',
            'remove_map_image' => '1',
        ])->assertRedirect();

        $this->assertNull($circle->fresh()->map_image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_product_image_can_be_uploaded_and_replaced(): void
    {
        Storage::fake('public');
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);
        ['circle' => $circle] = $this->makeCatalog($event, 'テスト', []);

        $this->actingAs($responsibles[0])->post(route('products.store', $circle), [
            'name' => '新刊',
            'price' => 1000,
            'image' => UploadedFile::fake()->image('cover.jpg'),
        ])->assertRedirect();

        $product = EventProduct::first();
        $first = $product->image_path;
        $this->assertNotNull($first);

        $this->actingAs($responsibles[0])->patch(route('products.update', $product), [
            'name' => '新刊',
            'price' => 1000,
            'image' => UploadedFile::fake()->image('cover2.jpg'),
        ])->assertRedirect();

        $second = $product->fresh()->image_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_oversized_or_invalid_files_are_rejected(): void
    {
        Storage::fake('public');
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);

        $this->actingAs($responsibles[0])->post(route('circles.store', $event), [
            'display_name' => 'テスト',
            'map_image' => UploadedFile::fake()->create('note.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('map_image');

        $this->assertSame(0, EventCircle::count());
    }

    public function test_deleting_a_circle_removes_its_images(): void
    {
        Storage::fake('public');
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);

        $this->actingAs($responsibles[0])->post(route('circles.store', $event), [
            'display_name' => '夏空スタジオ',
            'map_image' => UploadedFile::fake()->image('map.png'),
        ]);
        $circle = EventCircle::first();

        $this->actingAs($responsibles[0])->post(route('products.store', $circle), [
            'name' => '新刊',
            'price' => 1000,
            'image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $mapPath = $circle->fresh()->map_image_path;
        $productPath = EventProduct::first()->image_path;

        $this->actingAs($responsibles[0])->delete(route('circles.destroy', $circle))->assertRedirect();

        Storage::disk('public')->assertMissing($mapPath);
        Storage::disk('public')->assertMissing($productPath);
    }
}
