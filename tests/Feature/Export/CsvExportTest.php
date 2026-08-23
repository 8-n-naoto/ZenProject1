<?php

namespace Tests\Feature\Export;

use App\Models\Event;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private function settledEvent(): array
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = [$highests[0], $members[0], $members[1]];

        $event = $this->makeEvent($group, $highests[0], \App\Enums\EventStatus::Preparation, $participants);
        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => '夏空スタジオ', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1, 1 => 2], 'assignee' => 0],
        ]);

        return ['event' => $event, 'participants' => $participants, 'group' => $group];
    }

    public function test_purchase_results_csv_lists_every_item(): void
    {
        ['event' => $event, 'participants' => $participants] = $this->settledEvent();

        $csv = app(ExportService::class)->purchaseResultsCsv($event->fresh(), $participants[0]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"サークル"', $csv);
        $this->assertStringContainsString('"夏空スタジオ"', $csv);
        $this->assertStringContainsString('"新刊"', $csv);
        $this->assertStringContainsString('"共同"', $csv);
        $this->assertStringContainsString($participants[0]->name, $csv);
        // 3冊 x 1000円
        $this->assertStringContainsString('"3000"', $csv);
    }

    public function test_personal_rows_only_cover_the_downloader(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], \App\Enums\EventStatus::Accepting, [$highests[0], $members[0]]);
        ['products' => $products] = $this->makeCatalog($event, '夏空スタジオ', [
            ['name' => 'ぼくの新刊', 'price' => 1000],
            ['name' => 'あの人の新刊', 'price' => 1000],
        ]);

        $purchases = app(\App\Services\PurchaseListService::class);
        $purchases->savePersonalPurchases($event, $highests[0], [$products[0]->id => 1]);
        $purchases->savePersonalPurchases($event, $members[0], [$products[1]->id => 1]);

        $csv = app(ExportService::class)->purchaseResultsCsv($event->fresh(), $highests[0]);

        $this->assertStringContainsString('ぼくの新刊', $csv);
        $this->assertStringNotContainsString('あの人の新刊', $csv);
        $this->assertStringNotContainsString($members[0]->name, $csv);
    }

    public function test_settlements_csv_lists_transfers(): void
    {
        ['event' => $event] = $this->settledEvent();

        $csv = app(ExportService::class)->settlementsCsv($event->fresh());

        $this->assertStringContainsString('"支払う人"', $csv);
        $this->assertStringContainsString('"2000"', $csv);
        $this->assertStringContainsString('"pending"', $csv);
    }

    public function test_participant_can_download_both_files(): void
    {
        ['event' => $event, 'participants' => $participants] = $this->settledEvent();

        $this->actingAs($participants[1])
            ->get(route('events.export.results', $event))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($participants[1])
            ->get(route('events.export.settlements', $event))
            ->assertOk();
    }

    public function test_outsider_cannot_download(): void
    {
        ['event' => $event] = $this->settledEvent();
        $outsider = \App\Models\User::factory()->create();

        $this->actingAs($outsider)->get(route('events.export.results', $event))->assertForbidden();
        $this->actingAs($outsider)->get(route('events.export.settlements', $event))->assertForbidden();
    }

    public function test_formula_like_values_are_neutralised(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], \App\Enums\EventStatus::Accepting, [$highests[0]]);
        ['products' => $products] = $this->makeCatalog($event, '=cmd|test', [['name' => '+SUM(A1)', 'price' => 500]]);

        app(\App\Services\PurchaseListService::class)
            ->savePersonalPurchases($event, $highests[0], [$products[0]->id => 1]);

        $csv = app(ExportService::class)->purchaseResultsCsv($event->fresh(), $highests[0]);

        $this->assertStringContainsString("\"'=cmd|test\"", $csv);
        $this->assertStringContainsString("\"'+SUM(A1)\"", $csv);
    }

    public function test_file_name_falls_back_when_event_name_has_no_safe_characters(): void
    {
        $exports = app(ExportService::class);
        $event = new Event(['name' => '!!!']);

        $this->assertSame('event_精算.csv', $exports->fileName($event, '精算'));
    }
}
