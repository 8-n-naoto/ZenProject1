<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventCircle;
use App\Models\EventProduct;
use App\Models\Group;
use App\Models\GroupInviteLink;
use App\Models\Settlement;
use App\Models\SharedPurchase;
use App\Models\SharedPurchaseItem;
use App\Models\User;
use App\Services\GroupInviteLinkService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * すべての表示系ルートを実際に描画して、500 が出ないことを確認する。
 *
 * Blade の「渡し忘れた変数」「存在しないメソッド呼び出し」は
 * php -l でもテンプレートのコンパイルでも見つからず、実行して初めて落ちる。
 * （実際に `$approval->actionType`（正しくは `action_type`）で500になった事例がある）
 *
 * ルートを足したらこのテストが自動的に対象に含める。
 * 引数の作り方が分からないルートは「未確認」として明示的に失敗させる。
 */
class AllScreensRenderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 描画対象から外すルート名と、その理由。
     *
     * @var array<string, string>
     */
    private const SKIPPED = [
        'verification.verify' => '署名付きURLが必要。Auth\\EmailVerificationTest で確認している',
        'password.reset' => '有効なトークンが必要。Auth\\PasswordResetTest で確認している',
        'storage.local' => 'Laravel 標準のファイル配信',
    ];

    public function test_every_screen_renders(): void
    {
        $this->seed(DemoSeeder::class);

        $owner = User::firstWhere('user_id', 'owner001');
        $this->assertNotNull($owner, 'デモデータの owner001 が見つかりません');

        $parameters = $this->routeParameters($owner);

        $failures = [];
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $name = $route->getName();

            if ($name === null || isset(self::SKIPPED[$name])) {
                continue;
            }

            $uri = $route->uri();

            // ヘルスチェックなど、名前のないユーティリティは対象外
            if ($uri === 'up') {
                continue;
            }

            $bindings = [];
            $missing = [];

            foreach ($route->parameterNames() as $parameterName) {
                if (! array_key_exists($parameterName, $parameters)) {
                    $missing[] = $parameterName;

                    continue;
                }

                $bindings[$parameterName] = $parameters[$parameterName];
            }

            if ($missing !== []) {
                $failures[] = $name.': 引数 '.implode(', ', $missing).' の作り方が未定義です（このテストに追加してください）';

                continue;
            }

            $checked++;

            // 画面ごとにセッションを作り直す（AuthenticateSession 対策）
            $this->flushSession();

            try {
                $response = $this->actingAs($owner)->get(route($name, $bindings));
                $status = $response->getStatusCode();
            } catch (\Throwable $e) {
                $failures[] = $name.': 例外 '.$e::class.' — '.$e->getMessage();

                continue;
            }

            // 403/404 は権限や状態によっては正しい応答。500 系だけを不具合として扱う
            if ($status >= 500) {
                $failures[] = $name.': HTTP '.$status;
            }
        }

        $this->assertGreaterThan(35, $checked, '確認できた画面が少なすぎます（'.$checked.'件）');
        $this->assertSame([], $failures, "描画に失敗した画面:\n".implode("\n", $failures));
    }

    /**
     * ゲスト（未ログイン）でも開ける画面が壊れていないこと。
     */
    public function test_guest_screens_render(): void
    {
        $this->seed(DemoSeeder::class);

        $group = Group::firstOrFail();
        $owner = User::firstWhere('user_id', 'owner001');
        $link = app(GroupInviteLinkService::class)->issue($group, $owner);

        $this->flushSession();

        foreach ([
            route('login'),
            route('register'),
            route('password.request'),
            route('join.form'),
            route('join.show', $link->token),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    /**
     * ルートの引数に渡す値を用意する。
     *
     * @return array<string, mixed>
     */
    private function routeParameters(User $owner): array
    {
        $group = Group::firstOrFail();

        // 精算・購入結果まで進んでいるイベントを優先して選ぶ
        $settling = Event::where('status', EventStatus::Settling->value)->first();
        $event = $settling ?? Event::firstOrFail();

        $circle = EventCircle::where('event_id', $event->id)->first()
            ?? EventCircle::firstOrFail();

        $product = EventProduct::where('event_circle_id', $circle->id)->first()
            ?? EventProduct::firstOrFail();

        $sharedPurchase = SharedPurchase::where('event_id', $event->id)->first()
            ?? SharedPurchase::firstOrFail();

        $item = SharedPurchaseItem::where('shared_purchase_id', $sharedPurchase->id)->firstOrFail();

        $settlement = Settlement::where('event_id', $event->id)->first()
            ?? Settlement::firstOrFail();

        $link = GroupInviteLink::query()->first()
            ?? app(GroupInviteLinkService::class)->issue($group, $owner);

        return [
            'group' => $group,
            'event' => $event,
            'circle' => $circle,
            'product' => $product,
            'sharedPurchase' => $sharedPurchase,
            'item' => $item,
            'settlement' => $settlement,
            'user' => $owner,
            'token' => $link->token,
            'link' => $link,
        ];
    }
}
