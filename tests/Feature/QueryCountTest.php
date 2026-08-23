<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\User;
use App\Services\BulkCatalogImporter;
use App\Services\PurchaseListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 一覧系の画面が、データ件数に比例してクエリを増やさないことを確認する。
 * （N+1 の作り込みを防ぐための上限テスト）
 */
class QueryCountTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private function countQueries(callable $callback): int
    {
        $count = 0;

        DB::listen(function () use (&$count) {
            $count++;
        });

        $callback();

        return $count;
    }

    /**
     * サークル20件・商品各2件・参加者5人のイベントを作る。
     */
    private function largeEvent(): array
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 4]);
        $participants = array_merge($responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);

        $lines = [];
        for ($i = 1; $i <= 20; $i++) {
            $booth = sprintf('東%d ア-%02da', ($i % 3) + 1, $i);
            $lines[] = "サークル{$i}, {$booth}, 新刊{$i}, ".(500 + $i * 10);
            $lines[] = "サークル{$i}, {$booth}, グッズ{$i}, ".(300 + $i * 5);
        }

        $importer = app(BulkCatalogImporter::class);
        $parsed = $importer->parse(implode("\n", $lines));
        $importer->import($event, $parsed['rows']);

        $purchases = app(PurchaseListService::class);
        $products = $event->fresh()->eventProducts()->get();

        foreach ($participants as $index => $participant) {
            $quantities = [];
            foreach ($products->take(10) as $product) {
                $quantities[$product->id] = ($index % 2) + 1;
            }
            $purchases->savePersonalPurchases($event, $participant, $quantities);
        }

        $purchases->syncAll($event->fresh(), $responsibles[0]);

        foreach ($event->fresh()->sharedPurchases as $sharedPurchase) {
            $purchases->assign($sharedPurchase, $members[0], $responsibles[0]);
        }

        return compact('group', 'responsibles', 'members', 'event');
    }

    public function test_catalog_list_query_count_is_bounded(): void
    {
        ['event' => $event, 'responsibles' => $responsibles] = $this->largeEvent();

        $count = $this->countQueries(function () use ($event, $responsibles) {
            $this->actingAs($responsibles[0])->get(route('circles.index', $event))->assertOk();
        });

        $this->assertLessThan(25, $count, '実行されたクエリ数: '.$count);
    }

    public function test_wish_screen_query_count_is_bounded(): void
    {
        ['event' => $event, 'members' => $members] = $this->largeEvent();

        $count = $this->countQueries(function () use ($event, $members) {
            $this->actingAs($members[0])->get(route('purchases.personal.index', $event))->assertOk();
        });

        $this->assertLessThan(25, $count, '実行されたクエリ数: '.$count);
    }

    public function test_dashboard_query_count_is_bounded(): void
    {
        ['members' => $members] = $this->largeEvent();

        $count = $this->countQueries(function () use ($members) {
            $this->actingAs($members[0])->get(route('dashboard'))->assertOk();
        });

        $this->assertLessThan(40, $count, '実行されたクエリ数: '.$count);
    }

    public function test_group_screen_query_count_is_bounded(): void
    {
        ['group' => $group, 'responsibles' => $responsibles] = $this->largeEvent();

        $count = $this->countQueries(function () use ($group, $responsibles) {
            $this->actingAs($responsibles[0])->get(route('groups.show', $group))->assertOk();
        });

        $this->assertLessThan(30, $count, '実行されたクエリ数: '.$count);
    }

    /**
     * 未精算のまとめは、件数が増えてもクエリ数が増えないこと。
     */
    public function test_outstanding_summary_query_count_does_not_grow_with_rows(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup(['member' => 6]);
        $me = $members[0];

        // 1件だけのとき
        $small = $this->makeEvent($group, $highests[0], EventStatus::Preparation, [$highests[0], $me]);
        $this->runEventToSettlement($small, [$highests[0], $me], [
            ['circle' => '小さいサークル', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1, 1 => 1], 'assignee' => 0],
        ]);

        $baseline = $this->countQueries(function () use ($me) {
            $this->actingAs($me)->get(route('settlements.mine'))->assertOk();
        });

        // 未精算を増やす（精算リストは相殺されるため、イベントを増やして件数を増やす）
        for ($n = 0; $n < 4; $n++) {
            $other = $members[$n + 1];
            $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation, [$highests[0], $me, $other]);
            $this->runEventToSettlement($event, [$highests[0], $me, $other], [
                ['circle' => 'サークル'.$n, 'product' => '新刊'.$n, 'price' => 800 + $n * 10, 'wishes' => [0 => 1, 1 => 2, 2 => 1], 'assignee' => $n % 3],
            ]);
        }

        $rows = \App\Models\Settlement::query()
            ->where('status', \App\Enums\SettlementStatus::Pending->value)
            ->where(fn ($q) => $q->where('payer_user_id', $me->id)->orWhere('payee_user_id', $me->id))
            ->count();
        $this->assertGreaterThan(3, $rows, '未精算が増えていません');

        $grown = $this->countQueries(function () use ($me) {
            $this->actingAs($me)->get(route('settlements.mine'))->assertOk();
        });

        $this->assertLessThanOrEqual(
            $baseline + 2,
            $grown,
            "件数が増えるとクエリ数も増えています（1件: {$baseline} / {$rows}件: {$grown}）"
        );
        $this->assertLessThan(20, $grown, '実行されたクエリ数: '.$grown);
    }

    /**
     * サークル詳細に商品が複数あっても遅延ロードが起きないこと。
     * （preventLazyLoading は「複数行を取得したクエリ」でしか働かないため、
     *   商品1件だけのテストでは検出できない）
     */
    public function test_circle_detail_does_not_lazy_load(): void
    {
        ['group' => $group, 'responsibles' => $responsibles] = $this->largeEvent();
        $event = \App\Models\Event::where('group_id', $group->id)->firstOrFail();
        $circle = $event->eventCircles()->firstOrFail();

        $this->assertGreaterThan(1, $circle->eventProducts()->count(), '商品が複数ないと検証になりません');

        $this->actingAs($responsibles[0])
            ->get(route('circles.show', $circle))
            ->assertOk();
    }

    public function test_notifications_query_count_is_bounded(): void
    {
        $user = User::factory()->create();

        $rows = [];
        for ($i = 0; $i < 30; $i++) {
            $rows[] = [
                'user_id' => $user->id,
                'event_id' => null,
                'type' => 'invitation.received',
                'payload' => json_encode(['group' => 'グループ'.$i], JSON_UNESCAPED_UNICODE),
                'notified_at' => now()->subMinutes($i),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('notifications')->insert($rows);

        $count = $this->countQueries(function () use ($user) {
            $this->actingAs($user)->get(route('notifications.index'))->assertOk();
        });

        $this->assertLessThan(15, $count, '実行されたクエリ数: '.$count);
    }
}
