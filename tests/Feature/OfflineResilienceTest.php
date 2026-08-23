<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\PurchaseListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 会場は回線が混雑して繋がらない。そのときに
 * 「入力が消えない」「取得済みの内容が見られる」ことを確認する。
 */
class OfflineResilienceTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_forms_with_input_are_guarded(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $buyer = $highests[0];
        $wanter = $members[0];
        $event = $this->makeEvent($group, $buyer, EventStatus::Accepting, [$buyer, $wanter]);

        $catalog = app(CatalogService::class);
        $purchases = app(PurchaseListService::class);
        $circle = $catalog->createCircle($event, ['display_name' => '夏空スタジオ', 'booth' => '東1 ア-12a']);
        $product = $catalog->createProduct($circle, ['name' => '新刊', 'price' => 1000]);
        $purchases->savePersonalPurchases($event, $wanter, [$product->id => 2]);
        $shared = $purchases->syncSharedPurchaseFromWishes($circle->fresh(), $buyer);
        $purchases->assign($shared, $buyer, $buyer);

        // 購入希望（数量入力）
        $this->actingAs($wanter)->get(route('purchases.personal.index', $event))
            ->assertOk()
            ->assertSee('data-offline-guard="wishes:'.$event->id.'"', false);

        $event->update(['status' => EventStatus::Ongoing, 'fixed_at' => now()]);
        $item = $shared->fresh()->items()->firstOrFail();

        // 購入結果（数量入力）
        $this->actingAs($buyer)->get(route('results.edit', $item))
            ->assertOk()
            ->assertSee('data-offline-guard="result:'.$item->id.'"', false);
    }

    public function test_every_screen_carries_the_offline_guard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="offline-banner"', $html);
        $this->assertStringContainsString('電波が届いていません', $html);
        // 圏外での送信は止める
        $this->assertStringContainsString('navigator.onLine === false', $html);
    }

    public function test_the_shopping_list_saves_a_snapshot_for_offline_viewing(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $buyer = $highests[0];
        $wanter = $members[0];
        $event = $this->makeEvent($group, $buyer, EventStatus::Accepting, [$buyer, $wanter]);

        $catalog = app(CatalogService::class);
        $purchases = app(PurchaseListService::class);
        $circle = $catalog->createCircle($event, ['display_name' => '夏空スタジオ', 'booth' => '東1 ア-12a']);
        $product = $catalog->createProduct($circle, ['name' => '新刊イラスト集', 'price' => 1500]);
        $purchases->savePersonalPurchases($event, $wanter, [$product->id => 2]);
        $shared = $purchases->syncSharedPurchaseFromWishes($circle->fresh(), $buyer);
        $purchases->assign($shared, $buyer, $buyer);

        $event->update(['status' => EventStatus::Ongoing, 'fixed_at' => now()]);

        $html = $this->actingAs($buyer)->get(route('shopping.index', $event->fresh()))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="shopping-snapshot"', $html);
        $this->assertStringContainsString("sessionStorage.setItem('shopping-snapshot'", $html);

        // 控えの中身が正しいこと
        preg_match('#<script type="application/json" id="shopping-snapshot">(.*?)</script>#s', $html, $matches);
        $this->assertNotEmpty($matches, '控えのJSONが見つかりません');

        $snapshot = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($event->name, $snapshot['event']);
        $this->assertSame('夏空スタジオ', $snapshot['circles'][0]['name']);
        $this->assertSame('東1 ア-12a', $snapshot['circles'][0]['booth']);
        $this->assertSame('新刊イラスト集', $snapshot['circles'][0]['items'][0]['name']);
    }

    public function test_the_offline_page_can_show_the_saved_list(): void
    {
        $offline = (string) file_get_contents(public_path('offline.html'));

        $this->assertStringContainsString("sessionStorage.getItem('shopping-snapshot')", $offline);
        $this->assertStringContainsString('最後に取得した買い物リストです', $offline);
        $this->assertStringContainsString('ここからは記録できません', $offline);

        // 端末に残り続けないよう localStorage は使わない
        $this->assertStringNotContainsString('localStorage', $offline);
    }

    public function test_drafts_are_kept_in_session_storage_only(): void
    {
        $guard = (string) file_get_contents(resource_path('views/components/offline-guard.blade.php'));

        $this->assertStringContainsString('sessionStorage', $guard);

        // 共用端末に入力内容が残らないよう、localStorage は「使わない」（コメントでの言及は除く）
        $this->assertSame(
            0,
            preg_match('/(window\.)?localStorage\s*\./', $guard),
            'localStorage を使っています。タブを閉じたら消える sessionStorage を使ってください'
        );
    }
}
