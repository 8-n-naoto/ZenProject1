<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCircle;
use App\Models\Group;
use App\Models\SharedPurchase;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 画面のHTMLを書き出して、ブラウザでの見た目を確認できるようにする。
 *
 *   SNAPSHOT_DIR=/tmp/pages php artisan test --filter=PageSnapshotTest
 *
 * 通常のテスト実行では何もしない（SNAPSHOT_DIR が無ければスキップ）。
 */
class PageSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_render_pages(): void
    {
        $dir = env('SNAPSHOT_DIR');

        if (! $dir) {
            $this->markTestSkipped('SNAPSHOT_DIR が指定されていません。');
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->seed(DemoSeeder::class);

        $owner = User::firstWhere('user_id', 'owner001');
        $buyer = User::firstWhere('user_id', 'buyer001');
        $group = Group::first();
        $upcoming = Event::firstWhere('name', 'コミックマーケット105');
        $past = Event::firstWhere('name', 'コミックマーケット104');
        $circle = EventCircle::where('event_id', $upcoming->id)
            ->orderByRaw('map_image_path is null')
            ->orderBy('id')
            ->first();
        $sharedPurchase = SharedPurchase::where('event_id', $upcoming->id)->first();

        $pages = [
            'login' => [null, route('login')],
            'register' => [null, route('register')],
            'dashboard' => [$owner, route('dashboard')],
            'groups-index' => [$owner, route('groups.index')],
            'group-show' => [$owner, route('groups.show', $group)],
            'group-edit' => [$owner, route('groups.edit', $group)],
            'events-index' => [$owner, route('events.index', $group)],
            'event-show' => [$owner, route('events.show', $upcoming)],
            'event-create' => [$owner, route('events.create', $group)],
            'circles-index' => [$owner, route('circles.index', $upcoming)],
            'circle-show' => [$owner, route('circles.show', $circle)],
            'circle-create' => [$owner, route('circles.create', $upcoming)],
            'purchases-personal' => [$owner, route('purchases.personal.index', $upcoming)],
            'purchases-shared' => [$owner, route('purchases.shared.index', $upcoming)],
            'purchases-shared-show' => [$owner, route('purchases.shared.show', $sharedPurchase)],
            'purchases-summary' => [$owner, route('purchases.summary', $upcoming)],
            'settlements-past' => [$owner, route('settlements.index', $past)],
            'event-completed' => [$owner, route('events.show', $past)],
            'notifications' => [$buyer, route('notifications.index')],
            'profile' => [$owner, route('profile.edit')],
            'invitations' => [$buyer, route('invitations.index')],
            'approvals' => [$owner, route('approvals.index', $upcoming)],
            'history' => [$owner, route('histories.index', $upcoming)],
            'settlements-mine' => [$owner, route('settlements.mine')],
            'results-past' => [$owner, route('results.index', $past)],
            'circle-edit' => [$owner, route('circles.edit', $circle)],
            'event-map' => [$owner, route('events.map', $upcoming)],
        ];

        $css = file_get_contents(public_path('css/app.css'));
        $written = [];
        $failed = [];

        foreach ($pages as $name => [$user, $url]) {
            // ユーザーを切り替えるたびにセッションを作り直す（AuthenticateSession 対策）
            $this->flushSession();
            $request = $user !== null ? $this->actingAs($user) : $this;
            $response = $request->get($url);

            if ($response->getStatusCode() !== 200) {
                $written[] = $name.': HTTP '.$response->getStatusCode();
                $failed[] = $name.' → HTTP '.$response->getStatusCode();

                continue;
            }

            $html = $response->getContent();
            $html = preg_replace(
                '#<link rel="stylesheet" href="[^"]*"[^>]*>#',
                '<style>'.$css.'</style>',
                $html
            );

            file_put_contents($dir.'/'.$name.'.html', $html);
            $written[] = $name;
        }

        // 買い物リストは開催中でないと意味がないので、状態を進めてから描画する
        $upcoming->update(['status' => \App\Enums\EventStatus::Ongoing, 'fixed_at' => now()]);
        $this->flushSession();
        $response = $this->actingAs($buyer)->get(route('shopping.index', $upcoming->fresh()));

        if ($response->getStatusCode() === 200) {
            $html = preg_replace(
                '#<link rel="stylesheet" href="[^"]*"[^>]*>#',
                '<style>'.$css.'</style>',
                $response->getContent()
            );
            file_put_contents($dir.'/shopping-list.html', $html);
            $written[] = 'shopping-list';
        }

        file_put_contents($dir.'/index.txt', implode("\n", $written));

        $this->assertNotEmpty($written);
        $this->assertSame([], $failed, "描画に失敗した画面:\n".implode("\n", $failed));
    }
}
