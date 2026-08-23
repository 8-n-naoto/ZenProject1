<?php

namespace Tests\Feature\Catalog;

use App\Models\EventCircle;
use App\Models\EventProduct;
use App\Services\BulkCatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class BulkImportTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private function scenario(): array
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0]);

        return compact('group', 'responsibles', 'members', 'event');
    }

    public function test_circles_and_products_are_created(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $text = <<<'TEXT'
        夏空スタジオ, 東1 ア-12a, 新刊イラスト集, 1500
        夏空スタジオ, 東1 ア-12a, アクリルスタンド, 800
        ねこまた工房, 東2 ウ-05b
        星屑レコード, 西1 サ-31a, 新譜CD, ¥1,200
        TEXT;

        $this->actingAs($responsibles[0])
            ->post(route('circles.bulk.store', $event), ['text' => $text])
            ->assertRedirect(route('circles.index', $event));

        $this->assertSame(3, EventCircle::count());
        $this->assertSame(3, EventProduct::count());

        $natsuzora = EventCircle::firstWhere('display_name', '夏空スタジオ');
        $this->assertSame('東1 ア-12a', $natsuzora->booth);
        $this->assertSame(2, $natsuzora->eventProducts()->count());
        $this->assertSame(1200, EventProduct::firstWhere('name', '新譜CD')->price);
    }

    public function test_tab_separated_input_works(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $text = "夏空スタジオ\t東1 ア-12a\t新刊\t1500";

        $this->actingAs($responsibles[0])
            ->post(route('circles.bulk.store', $event), ['text' => $text])
            ->assertRedirect();

        $this->assertSame(1, EventCircle::count());
        $this->assertSame(1500, EventProduct::first()->price);
    }

    public function test_full_width_prices_are_accepted(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $this->actingAs($responsibles[0])
            ->post(route('circles.bulk.store', $event), ['text' => '夏空スタジオ, 東1 ア-12a, 新刊, １５００'])
            ->assertRedirect();

        $this->assertSame(1500, EventProduct::first()->price);
    }

    public function test_missing_price_is_reported_with_the_line_number(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $text = "夏空スタジオ, 東1 ア-12a, 新刊, 1500\nねこまた工房, 東2 ウ-05b, 短編集";

        $this->actingAs($responsibles[0])
            ->post(route('circles.bulk.store', $event), ['text' => $text])
            ->assertSessionHasErrors('text');

        $this->assertSame(0, EventCircle::count());
        $this->assertStringContainsString('2行目', session('errors')->get('text')[0]);
    }

    public function test_comment_and_blank_lines_are_ignored(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $text = "# 1日目\n\n夏空スタジオ, 東1 ア-12a\n\n# 2日目\nねこまた工房, 東2 ウ-05b";

        $this->actingAs($responsibles[0])
            ->post(route('circles.bulk.store', $event), ['text' => $text])
            ->assertRedirect();

        $this->assertSame(2, EventCircle::count());
    }

    public function test_existing_circles_gain_products_instead_of_duplicates(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();
        $this->makeCatalog($event, '夏空スタジオ', [['name' => '既刊', 'price' => 500]]);

        $this->actingAs($responsibles[0])
            ->post(route('circles.bulk.store', $event), ['text' => '夏空 スタジオ, 東1 ア-12a, 新刊, 1500'])
            ->assertRedirect();

        $this->assertSame(1, EventCircle::count());
        $this->assertSame(2, EventProduct::count());
    }

    public function test_duplicate_products_are_skipped(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $text = "夏空スタジオ, 東1 ア-12a, 新刊, 1500\n夏空スタジオ, 東1 ア-12a, 新刊, 1500";

        $this->actingAs($responsibles[0])
            ->post(route('circles.bulk.store', $event), ['text' => $text])
            ->assertRedirect();

        $this->assertSame(1, EventProduct::count());
    }

    public function test_general_member_cannot_bulk_import(): void
    {
        ['event' => $event, 'members' => $members] = $this->scenario();

        $this->actingAs($members[0])
            ->post(route('circles.bulk.store', $event), ['text' => '夏空スタジオ'])
            ->assertForbidden();
    }

    public function test_empty_input_is_rejected(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $this->actingAs($responsibles[0])
            ->post(route('circles.bulk.store', $event), ['text' => "\n\n"])
            ->assertSessionHasErrors('text');
    }

    public function test_bulk_form_renders(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->scenario();

        $this->actingAs($responsibles[0])
            ->get(route('circles.bulk.form', $event))
            ->assertOk()
            ->assertSee('まとめて登録');
    }

    public function test_parser_reports_multiple_errors(): void
    {
        $parsed = app(BulkCatalogImporter::class)->parse(", , ,\n夏空, 東1, 新刊");

        $this->assertNotEmpty($parsed['errors']);
        $this->assertCount(2, $parsed['errors']);
    }
}
