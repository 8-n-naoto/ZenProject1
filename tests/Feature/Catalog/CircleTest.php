<?php

namespace Tests\Feature\Catalog;

use App\Enums\EventStatus;
use App\Models\Circle;
use App\Models\EventCircle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class CircleTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'display_name' => '夏空スタジオ',
            'booth' => '東1ホール ア-12a',
            'website_url' => 'https://example.com',
            'description' => '新刊あり',
        ], $overrides);
    }

    public function test_responsible_can_register_circle_during_preparation(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);

        $this->actingAs($responsibles[0])
            ->post(route('circles.store', $event), $this->payload())
            ->assertRedirect();

        $circle = EventCircle::first();
        $this->assertNotNull($circle);
        $this->assertSame('夏空スタジオ', $circle->display_name);
        $this->assertSame('夏空スタジオ', Circle::first()->name);
    }

    public function test_general_participant_cannot_register_during_preparation(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Preparation, $members);

        $this->actingAs($members[0])
            ->post(route('circles.store', $event), $this->payload())
            ->assertForbidden();
    }

    public function test_participant_can_register_while_accepting(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $members);

        $this->actingAs($members[0])
            ->post(route('circles.store', $event), $this->payload())
            ->assertRedirect();

        $this->assertSame(1, EventCircle::count());
    }

    public function test_non_participant_member_cannot_register_while_accepting(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting);

        $this->actingAs($members[0])
            ->post(route('circles.store', $event), $this->payload())
            ->assertForbidden();
    }

    public function test_catalog_is_locked_after_fixing(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Fixed, $members);

        $this->actingAs($responsibles[0])
            ->post(route('circles.store', $event), $this->payload())
            ->assertForbidden();
    }

    public function test_duplicate_circle_name_is_detected(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);

        $this->actingAs($responsibles[0])->post(route('circles.store', $event), $this->payload());

        $this->actingAs($responsibles[0])
            ->post(route('circles.store', $event), $this->payload())
            ->assertSessionHasErrors('display_name');

        $this->assertSame(1, EventCircle::count());
    }

    public function test_duplicate_detection_ignores_spacing_and_width(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);

        $this->actingAs($responsibles[0])
            ->post(route('circles.store', $event), $this->payload(['display_name' => 'ＡＢＣ スタジオ']));

        $this->actingAs($responsibles[0])
            ->post(route('circles.store', $event), $this->payload(['display_name' => 'abc・スタジオ']))
            ->assertSessionHasErrors('display_name');

        $this->assertSame(1, EventCircle::count());
    }

    public function test_duplicate_can_be_registered_with_force_flag(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);

        $this->actingAs($responsibles[0])->post(route('circles.store', $event), $this->payload());

        $this->actingAs($responsibles[0])
            ->post(route('circles.store', $event), $this->payload(['force' => '1']))
            ->assertRedirect();

        $this->assertSame(2, EventCircle::count());
    }

    public function test_duplicate_detection_is_scoped_to_the_event(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $eventA = $this->makeEvent($group, $responsibles[0]);
        $eventB = $this->makeEvent($group, $responsibles[0]);

        $this->actingAs($responsibles[0])->post(route('circles.store', $eventA), $this->payload());

        $this->actingAs($responsibles[0])
            ->post(route('circles.store', $eventB), $this->payload())
            ->assertRedirect();

        $this->assertSame(2, EventCircle::count());
    }

    public function test_circle_can_be_updated_and_deleted(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);
        $this->actingAs($responsibles[0])->post(route('circles.store', $event), $this->payload());
        $circle = EventCircle::first();

        $this->actingAs($responsibles[0])
            ->patch(route('circles.update', $circle), $this->payload(['display_name' => '冬空スタジオ']))
            ->assertRedirect();
        $this->assertSame('冬空スタジオ', $circle->fresh()->display_name);

        $this->actingAs($responsibles[0])
            ->delete(route('circles.destroy', $circle))
            ->assertRedirect(route('circles.index', $event));
        $this->assertSame(0, EventCircle::count());
    }

    public function test_updating_to_an_existing_name_is_detected(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);
        $this->actingAs($responsibles[0])->post(route('circles.store', $event), $this->payload(['display_name' => 'A社']));
        $this->actingAs($responsibles[0])->post(route('circles.store', $event), $this->payload(['display_name' => 'B社']));
        $second = EventCircle::orderByDesc('id')->first();

        $this->actingAs($responsibles[0])
            ->patch(route('circles.update', $second), $this->payload(['display_name' => 'A社']))
            ->assertSessionHasErrors('display_name');
    }

    public function test_circle_screens_render(): void
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);
        $this->actingAs($responsibles[0])->post(route('circles.store', $event), $this->payload());
        $circle = EventCircle::first();

        $this->actingAs($responsibles[0])->get(route('circles.index', $event))->assertOk()->assertSee('夏空スタジオ');
        $this->actingAs($responsibles[0])->get(route('circles.create', $event))->assertOk();
        $this->actingAs($responsibles[0])->get(route('circles.show', $circle))->assertOk();
        $this->actingAs($responsibles[0])->get(route('circles.edit', $circle))->assertOk();
    }
}
