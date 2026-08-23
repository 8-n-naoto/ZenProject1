<?php

namespace Tests\Feature\Catalog;

use App\Enums\EventStatus;
use App\Enums\ProductStatus;
use App\Models\EventCircle;
use App\Models\EventProduct;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private function makeCircle(EventStatus $status = EventStatus::Preparation): array
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], $status, $members);

        $circle = app(\App\Services\CatalogService::class)->createCircle($event, [
            'display_name' => '夏空スタジオ',
            'booth' => '東1 ア-12a',
        ]);

        return compact('group', 'responsibles', 'members', 'event', 'circle');
    }

    public function test_product_can_be_registered(): void
    {
        ['responsibles' => $responsibles, 'circle' => $circle] = $this->makeCircle();

        $this->actingAs($responsibles[0])
            ->post(route('products.store', $circle), [
                'name' => '新刊イラスト集',
                'price' => 1500,
                'description' => 'B5・48ページ',
            ])
            ->assertRedirect(route('circles.show', $circle));

        $product = EventProduct::first();

        $this->assertSame('新刊イラスト集', $product->name);
        $this->assertSame(1500, $product->price);
        $this->assertSame(ProductStatus::Selling, $product->status);
        $this->assertSame($circle->event_id, $product->event_id);
        $this->assertSame('新刊イラスト集', Product::first()->name);
    }

    public function test_price_must_be_a_non_negative_integer(): void
    {
        ['responsibles' => $responsibles, 'circle' => $circle] = $this->makeCircle();

        $this->actingAs($responsibles[0])
            ->post(route('products.store', $circle), ['name' => 'A', 'price' => -100])
            ->assertSessionHasErrors('price');

        $this->actingAs($responsibles[0])
            ->post(route('products.store', $circle), ['name' => 'A', 'price' => 'ごひゃくえん'])
            ->assertSessionHasErrors('price');

        $this->assertSame(0, EventProduct::count());
    }

    public function test_price_can_be_zero(): void
    {
        ['responsibles' => $responsibles, 'circle' => $circle] = $this->makeCircle();

        $this->actingAs($responsibles[0])
            ->post(route('products.store', $circle), ['name' => '無料配布', 'price' => 0])
            ->assertRedirect();

        $this->assertSame(0, EventProduct::first()->price);
    }

    public function test_product_can_be_updated(): void
    {
        ['responsibles' => $responsibles, 'circle' => $circle] = $this->makeCircle();
        $this->actingAs($responsibles[0])->post(route('products.store', $circle), ['name' => 'A', 'price' => 500]);
        $product = EventProduct::first();

        $this->actingAs($responsibles[0])
            ->patch(route('products.update', $product), [
                'name' => 'B',
                'price' => 800,
                'status' => ProductStatus::SoldOut->value,
            ])
            ->assertRedirect();

        $fresh = $product->fresh();
        $this->assertSame('B', $fresh->name);
        $this->assertSame(800, $fresh->price);
        $this->assertSame(ProductStatus::SoldOut, $fresh->status);
    }

    public function test_product_can_be_deleted(): void
    {
        ['responsibles' => $responsibles, 'circle' => $circle] = $this->makeCircle();
        $this->actingAs($responsibles[0])->post(route('products.store', $circle), ['name' => 'A', 'price' => 500]);
        $product = EventProduct::first();

        $this->actingAs($responsibles[0])
            ->delete(route('products.destroy', $product))
            ->assertRedirect();

        $this->assertSoftDeleted('event_products', ['id' => $product->id]);
    }

    public function test_product_cannot_be_registered_after_fixing(): void
    {
        ['responsibles' => $responsibles, 'circle' => $circle] = $this->makeCircle();
        $circle->event->update(['status' => EventStatus::Fixed]);

        $this->actingAs($responsibles[0])
            ->post(route('products.store', $circle->fresh()), ['name' => 'A', 'price' => 500])
            ->assertForbidden();
    }

    public function test_product_screens_render(): void
    {
        ['responsibles' => $responsibles, 'circle' => $circle] = $this->makeCircle();
        $this->actingAs($responsibles[0])->post(route('products.store', $circle), ['name' => '新刊', 'price' => 1200]);
        $product = EventProduct::first();

        $this->actingAs($responsibles[0])->get(route('products.create', $circle))->assertOk();
        $this->actingAs($responsibles[0])->get(route('products.edit', $product))->assertOk();
        $this->actingAs($responsibles[0])
            ->get(route('circles.show', $circle))
            ->assertOk()
            ->assertSee('新刊')
            ->assertSee('¥1,200');
    }

    public function test_deleted_circle_removes_its_products(): void
    {
        ['responsibles' => $responsibles, 'circle' => $circle] = $this->makeCircle();
        $this->actingAs($responsibles[0])->post(route('products.store', $circle), ['name' => 'A', 'price' => 500]);

        $this->actingAs($responsibles[0])->delete(route('circles.destroy', $circle))->assertRedirect();

        $this->assertSame(0, EventProduct::count());
        $this->assertSame(0, EventCircle::count());
    }
}
