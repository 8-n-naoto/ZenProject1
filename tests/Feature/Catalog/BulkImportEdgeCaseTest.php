<?php

namespace Tests\Feature\Catalog;

use App\Enums\EventStatus;
use App\Services\BulkCatalogImporter;
use App\Support\BoothSorter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class BulkImportEdgeCaseTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_full_width_commas_are_treated_as_separators(): void
    {
        $parsed = app(BulkCatalogImporter::class)->parse('夏空スタジオ，東1 ア-12a，新刊イラスト集，1500');

        $this->assertSame([], $parsed['errors']);
        $this->assertSame('夏空スタジオ', $parsed['rows'][0]['circle']);
        $this->assertSame('東1 ア-12a', $parsed['rows'][0]['booth']);
        $this->assertSame('新刊イラスト集', $parsed['rows'][0]['product']);
        $this->assertSame(1500, $parsed['rows'][0]['price']);
    }

    public function test_negative_prices_are_rejected(): void
    {
        $parsed = app(BulkCatalogImporter::class)->parse('夏空スタジオ,東1 ア-12a,新刊,-500');

        $this->assertSame([], $parsed['rows']);
        $this->assertCount(1, $parsed['errors']);
        $this->assertStringContainsString('マイナス', $parsed['errors'][0]);
    }

    public function test_overlong_product_names_are_rejected(): void
    {
        $long = str_repeat('あ', 150);
        $parsed = app(BulkCatalogImporter::class)->parse('夏空スタジオ,東1 ア-12a,'.$long.',500');

        $this->assertSame([], $parsed['rows']);
        $this->assertStringContainsString('商品名が長すぎます', $parsed['errors'][0]);
    }

    public function test_importing_many_rows_does_not_scale_queries_with_row_count(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation);

        $lines = [];
        for ($i = 1; $i <= 60; $i++) {
            $lines[] = "サークル{$i},東1 ア-".sprintf('%02d', $i).'a,新刊'.$i.','.(500 + $i);
        }

        $importer = app(BulkCatalogImporter::class);
        $parsed = $importer->parse(implode("\n", $lines));
        $this->assertSame([], $parsed['errors']);

        $queries = 0;
        \Illuminate\Support\Facades\DB::listen(function () use (&$queries) {
            $queries++;
        });

        $result = $importer->import($event, $parsed['rows']);

        $this->assertSame(60, $result['circles']);
        $this->assertSame(60, $result['products']);
        // 1行あたり6クエリだと 360 を超える。商品の重複判定をメモリで行うことで抑えている
        $this->assertLessThan(60 * 5, $queries, '実行されたクエリ数: '.$queries);
    }

    public function test_booth_sorter_handles_hall_notation(): void
    {
        $sorted = BoothSorter::sort(['西2 サ-05b', '西2ホール ア-01a'], fn (string $booth) => $booth);

        $this->assertSame(['西2ホール ア-01a', '西2 サ-05b'], array_values($sorted));
    }

    public function test_booth_sorter_keeps_unparseable_booths_at_the_end(): void
    {
        $sorted = BoothSorter::sort(['オンライン', '東1 ア-01a', null], fn (?string $booth) => $booth);

        $this->assertSame('東1 ア-01a', array_values($sorted)[0]);
        $this->assertNull(array_values($sorted)[2]);
    }
}
