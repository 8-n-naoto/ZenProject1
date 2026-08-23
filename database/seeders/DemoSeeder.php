<?php

namespace Database\Seeders;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\EventService;
use App\Services\PurchaseListService;
use App\Services\PurchaseResultService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 手動確認用のデモデータ。
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * ログインID: owner001 / leader01 / buyer001 / member01
 * パスワード: password
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = collect([
            'owner001' => '主催 太郎',
            'leader01' => '責任 花子',
            'buyer001' => '購入 次郎',
            'member01' => '一般 三郎',
        ])->map(fn (string $name, string $userId) => User::firstOrCreate(
            ['user_id' => $userId],
            [
                'name' => $name,
                'email' => $userId.'@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        ));

        [$owner, $leader, $buyer, $member] = $users->values()->all();

        $group = Group::firstOrCreate(['name' => '冬コミ有志の会'], [
            'description' => 'デモ用のグループです。コミケの共同購入を管理します。',
        ]);

        $roles = [
            $owner->id => GroupRole::HighestResponsible,
            $leader->id => GroupRole::Responsible,
            $buyer->id => GroupRole::Member,
            $member->id => GroupRole::Member,
        ];

        foreach ($roles as $userId => $role) {
            if ($group->members()->where('users.id', $userId)->exists()) {
                continue;
            }

            $group->members()->attach($userId, ['role' => $role->value, 'joined_at' => now()]);
        }

        if ($group->events()->exists()) {
            $this->command?->info('デモデータはすでに作成済みです。');

            return;
        }

        $events = app(EventService::class);
        $catalog = app(CatalogService::class);
        $purchases = app(PurchaseListService::class);
        $results = app(PurchaseResultService::class);

        /* 進行中のイベント（受付中） */
        $upcoming = $events->create($group, $leader, [
            'name' => 'コミックマーケット105',
            'venue_name' => '東京ビッグサイト',
            'venue_address' => '東京都江東区有明3-11-1',
            'description' => "10:00 に西ホール入口集合。\n買い物リストは前日までに登録してください。",
            'days' => [
                ['event_date' => now()->addDays(30)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '16:00'],
                ['event_date' => now()->addDays(31)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '16:00'],
            ],
        ]);

        $events->advance($upcoming, $leader);

        foreach ([$owner, $leader, $buyer, $member] as $participant) {
            $events->join($upcoming, $participant);
        }

        $catalogPlan = [
            ['夏空スタジオ', '東1 ア-12a', [['新刊イラスト集', 1500], ['アクリルスタンド', 800]]],
            ['ねこまた工房', '東2 ウ-05b', [['setsuna 短編集', 700]]],
            ['星屑レコード', '西1 サ-31a', [['新譜CD', 1200], ['缶バッジセット', 500]]],
        ];

        $products = [];

        foreach ($catalogPlan as [$name, $booth, $items]) {
            $circle = $catalog->createCircle($upcoming, ['display_name' => $name, 'booth' => $booth]);

            foreach ($items as [$productName, $price]) {
                $products[] = $catalog->createProduct($circle, ['name' => $productName, 'price' => $price]);
            }
        }

        $purchases->savePersonalPurchases($upcoming, $owner, [
            $products[0]->id => 1, $products[2]->id => 1, $products[3]->id => 2,
        ]);
        $purchases->savePersonalPurchases($upcoming, $leader, [
            $products[0]->id => 1, $products[1]->id => 1,
        ]);
        $purchases->savePersonalPurchases($upcoming, $member, [
            $products[3]->id => 1, $products[4]->id => 3,
        ]);

        $purchases->syncAll($upcoming, $leader);

        foreach ($upcoming->fresh()->sharedPurchases as $index => $sharedPurchase) {
            $assignee = [$buyer, $leader, $owner][$index % 3];
            $purchases->assign($sharedPurchase, $assignee, $leader);
        }

        // 1サークルだけ「列を分ける」例として、商品単位の担当を割り当てる
        $splitTarget = $upcoming->fresh()->sharedPurchases
            ->first(fn ($sharedPurchase) => $sharedPurchase->items()->count() > 0);

        if ($splitTarget !== null) {
            $item = $splitTarget->items()->first();

            if ($item !== null && $item->planned_quantity >= 2) {
                $purchases->syncProductAssignees($item, [
                    $buyer->id => 1,
                    $member->id => 1,
                ], $leader);
            }
        }

        /* 完了済みのイベント（閲覧制御の確認用） */
        $past = $events->create($group, $leader, [
            'name' => 'コミックマーケット104',
            'venue_name' => '東京ビッグサイト',
            'description' => '前回のイベント（完了済み）',
            'days' => [
                ['event_date' => now()->subDays(60)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '16:00'],
            ],
        ]);

        $events->advance($past, $leader);

        foreach ([$owner, $leader, $buyer] as $participant) {
            $events->join($past, $participant);
        }

        $pastCircle = $catalog->createCircle($past, ['display_name' => 'よぞら文庫', 'booth' => '東3 キ-01a']);
        $pastProduct = $catalog->createProduct($pastCircle, ['name' => '既刊セット', 'price' => 2000]);

        $purchases->savePersonalPurchases($past, $owner, [$pastProduct->id => 1]);
        $purchases->savePersonalPurchases($past, $leader, [$pastProduct->id => 1]);
        $purchases->syncAll($past, $leader);

        $pastShared = $past->fresh()->sharedPurchases->first();
        $purchases->assign($pastShared, $buyer, $leader);

        $events->advance($past->fresh(), $leader);   // 確定済
        $events->advance($past->fresh(), $leader);   // 開催中

        $results->recordForSharedItem($pastShared->items()->first(), $buyer, 2, null);

        $events->advance($past->fresh(), $leader);   // 精算中

        foreach ($past->fresh()->settlements()->with(['payer', 'payee'])->get() as $settlement) {
            $payment = app(\App\Services\SettlementService::class)->reportPayment($settlement, $settlement->payer);
            app(\App\Services\SettlementService::class)->confirmPayment($payment, $settlement->payee);
        }

        $events->advance($past->fresh(), $owner);    // 完了

        /* 精算中のイベント（未精算のまとめの確認用） */
        $settling = $events->create($group, $leader, [
            'name' => 'コミックマーケット103',
            'venue_name' => '東京ビッグサイト',
            'description' => '精算待ちのイベント',
            'days' => [
                ['event_date' => now()->subDays(20)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '16:00'],
            ],
        ]);

        $events->advance($settling, $leader);

        foreach ([$owner, $leader, $buyer] as $participant) {
            $events->join($settling, $participant);
        }

        $settlingCircle = $catalog->createCircle($settling, ['display_name' => 'ひだまり書房', 'booth' => '南2 コ-14b']);
        $settlingProduct = $catalog->createProduct($settlingCircle, ['name' => '画集', 'price' => 1800]);

        $purchases->savePersonalPurchases($settling, $owner, [$settlingProduct->id => 1]);
        $purchases->savePersonalPurchases($settling, $buyer, [$settlingProduct->id => 2]);
        $purchases->syncAll($settling, $leader);

        $settlingShared = $settling->fresh()->sharedPurchases->first();
        $purchases->assign($settlingShared, $leader, $leader);

        $events->advance($settling->fresh(), $leader);   // 確定済
        $events->advance($settling->fresh(), $leader);   // 開催中

        $results->recordForSharedItem($settlingShared->items()->first(), $leader, 3, null);

        $events->advance($settling->fresh(), $leader);   // 精算中（支払いは未報告のまま残す）

        /* 会場図とサークルの位置（会場マップ画面の確認用） */
        $venueMap = $this->makeSampleMap('venue-maps/demo-venue.png', 900, 600);

        if ($venueMap !== null) {
            $upcoming->update(['map_image_path' => $venueMap]);

            $positions = [
                '夏空スタジオ' => [22, 28],
                'ねこまた工房' => [58, 45],
                '星屑レコード' => [78, 72],
            ];

            foreach ($upcoming->fresh()->eventCircles as $eventCircle) {
                if (isset($positions[$eventCircle->display_name])) {
                    [$x, $y] = $positions[$eventCircle->display_name];
                    $eventCircle->update(['venue_map_x' => $x, 'venue_map_y' => $y]);
                }
            }
        }

        /* 完売リスク（巡回ルートの並びの確認用） */
        $risks = ['夏空スタジオ' => 'high', 'ねこまた工房' => 'medium', '星屑レコード' => 'low'];

        foreach ($upcoming->fresh()->eventCircles as $eventCircle) {
            if (isset($risks[$eventCircle->display_name])) {
                $eventCircle->update(['sellout_risk' => $risks[$eventCircle->display_name]]);
            }
        }

        /* 参加者ごとの予算（当日画面の残高表示の確認用） */
        foreach ([$owner->id => 20000, $leader->id => 15000, $buyer->id => 30000] as $userId => $budget) {
            $upcoming->participants()->updateExistingPivot($userId, ['budget' => $budget]);
        }

        /* 配置マップの画像とピン（サンプル） */
        $mapPath = $this->makeSampleMap();

        if ($mapPath !== null) {
            $mapped = $upcoming->fresh()->eventCircles()->orderBy('id')->first();
            $mapped?->update(['map_image_path' => $mapPath, 'map_x' => 34, 'map_y' => 58]);
        }

        $this->command?->info('デモデータを作成しました。ログインID: owner001 / leader01 / buyer001 / member01（パスワード: password）');
    }

    /**
     * 配置図のサンプル画像を作る（GD が無い環境では何もしない）。
     */
    private function makeSampleMap(string $path = 'circles/demo-map.png', int $width = 600, int $height = 400): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $image = imagecreatetruecolor($width, $height);
        $paper = imagecolorallocate($image, 248, 250, 252);
        $line = imagecolorallocate($image, 148, 163, 184);
        $block = imagecolorallocate($image, 203, 213, 225);

        imagefilledrectangle($image, 0, 0, $width, $height, $paper);

        $columns = max(3, (int) floor(($width - 40) / 110));
        $rows = max(3, (int) floor(($height - 40) / 90));

        for ($row = 0; $row < $rows; $row++) {
            for ($column = 0; $column < $columns; $column++) {
                $x = 40 + $column * 110;
                $y = 40 + $row * 90;

                if ($x + 80 > $width || $y + 55 > $height) {
                    continue;
                }
                imagefilledrectangle($image, $x, $y, $x + 80, $y + 55, $block);
                imagerectangle($image, $x, $y, $x + 80, $y + 55, $line);
            }
        }

        $absolute = storage_path('app/public/'.$path);

        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0755, true);
        }

        imagepng($image, $absolute);
        imagedestroy($image);

        return $path;
    }
}
