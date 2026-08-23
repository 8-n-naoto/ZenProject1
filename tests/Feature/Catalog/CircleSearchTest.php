<?php

namespace Tests\Feature\Catalog;

use App\Services\CatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class CircleSearchTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private function scenario(): array
    {
        ['group' => $group, 'responsible' => $responsibles] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);

        $catalog = app(CatalogService::class);

        $a = $catalog->createCircle($event, ['display_name' => '星屑レコード', 'booth' => '西2 サ-05b']);
        $catalog->createProduct($a, ['name' => '新譜CD', 'price' => 1200]);

        $b = $catalog->createCircle($event, ['display_name' => '夏空スタジオ', 'booth' => '東1 ア-12a']);
        $catalog->createProduct($b, ['name' => 'イラスト集', 'price' => 1500]);

        $c = $catalog->createCircle($event, ['display_name' => 'ねこまた工房', 'booth' => '東1 ア-03b']);

        return compact('group', 'responsibles', 'event');
    }

    public function test_default_order_is_by_booth(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $content = $this->actingAs($responsibles[0])
            ->get(route('circles.index', $event))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(mb_strpos($content, '夏空スタジオ'), mb_strpos($content, 'ねこまた工房'));
        $this->assertLessThan(mb_strpos($content, '星屑レコード'), mb_strpos($content, '夏空スタジオ'));
    }

    public function test_name_order_can_be_selected(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $this->actingAs($responsibles[0])
            ->get(route('circles.index', ['event' => $event, 'sort' => 'name']))
            ->assertOk()
            ->assertSee('名前順');
    }

    public function test_search_matches_circle_name(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $this->actingAs($responsibles[0])
            ->get(route('circles.index', ['event' => $event, 'q' => '夏空']))
            ->assertOk()
            ->assertSee('夏空スタジオ')
            ->assertDontSee('星屑レコード');
    }

    public function test_search_matches_booth(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $this->actingAs($responsibles[0])
            ->get(route('circles.index', ['event' => $event, 'q' => 'サ-05']))
            ->assertOk()
            ->assertSee('星屑レコード')
            ->assertDontSee('ねこまた工房');
    }

    public function test_search_matches_product_name(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $this->actingAs($responsibles[0])
            ->get(route('circles.index', ['event' => $event, 'q' => 'イラスト集']))
            ->assertOk()
            ->assertSee('夏空スタジオ')
            ->assertDontSee('ねこまた工房');
    }

    public function test_no_match_shows_a_message(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $this->actingAs($responsibles[0])
            ->get(route('circles.index', ['event' => $event, 'q' => 'そんなサークルはない']))
            ->assertOk()
            ->assertSee('該当するサークルがありません');
    }
}
