<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\GroupRole;
use App\Enums\PaymentStatus;
use App\Enums\SettlementStatus;
use App\Models\Approval;
use App\Models\Event;
use App\Models\EventCircle;
use App\Models\EventProduct;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\SharedPurchase;
use App\Models\SharedPurchaseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 実際の利用の流れをHTTPリクエストだけで通しで検証する。
 *
 * グループ作成 → メンバー招待 → 役割任命 → イベント作成 → 受付 → 参加表明
 * → サークル・商品登録 → 購入希望 → 共同購入リスト → 担当者確定 → 確定
 * → 購入結果登録（不足あり） → 精算 → 支払い・受取確認 → イベント完了
 */
class FullScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_group_can_run_an_event_from_start_to_finish(): void
    {
        /* ---------------------------------------------------------- */
        /* 1. ユーザー登録 */
        /* ---------------------------------------------------------- */
        $owner = User::factory()->create(['user_id' => 'owner001', 'name' => '主催 太郎']);
        $leader = User::factory()->create(['user_id' => 'leader01', 'name' => '責任 花子']);
        $buyer = User::factory()->create(['user_id' => 'buyer001', 'name' => '購入 次郎']);

        /* ---------------------------------------------------------- */
        /* 2. グループ作成（作成者が最高責任者として自動参加） */
        /* ---------------------------------------------------------- */
        $this->actingAs($owner)
            ->post(route('groups.store'), ['name' => '冬コミ有志の会', 'description' => 'テスト'])
            ->assertRedirect();

        $group = Group::firstWhere('name', '冬コミ有志の会');
        $this->assertSame(GroupRole::HighestResponsible, $group->roleOf($owner));

        /* ---------------------------------------------------------- */
        /* 3. メンバーを招待して参加してもらう */
        /* ---------------------------------------------------------- */
        foreach ([$leader, $buyer] as $invitee) {
            $this->actingAs($owner)->post(route('groups.invite', [$group, $invitee]))->assertRedirect();
            $invitation = Invitation::where('invited_user_id', $invitee->id)->latest('id')->first();
            $this->actingAs($invitee)->post(route('invitations.accept', $invitation))->assertRedirect();
        }

        $this->assertSame(3, $group->fresh()->activeMemberCount());

        /* ---------------------------------------------------------- */
        /* 4. 責任者を任命（イベント作成の前提条件） */
        /* ---------------------------------------------------------- */
        $this->actingAs($owner)
            ->patch(route('groups.members.role.update', [$group, $leader]), ['role' => GroupRole::Responsible->value])
            ->assertRedirect();

        $this->assertSame(GroupRole::Responsible, $group->fresh()->roleOf($leader));

        /* ---------------------------------------------------------- */
        /* 5. イベント作成 */
        /* ---------------------------------------------------------- */
        $this->actingAs($leader)->post(route('events.store', $group), [
            'name' => 'コミックマーケット105',
            'venue_name' => '東京ビッグサイト',
            'days' => [
                ['event_date' => now()->addDays(30)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '16:00'],
                ['event_date' => now()->addDays(31)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '16:00'],
            ],
        ])->assertRedirect();

        $event = Event::first();
        $this->assertSame(EventStatus::Preparation, $event->status);
        $this->assertSame(2, $event->days()->count());

        /* ---------------------------------------------------------- */
        /* 6. 受付開始 → 全員が参加表明 */
        /* ---------------------------------------------------------- */
        $this->actingAs($leader)->post(route('events.advance', $event))->assertRedirect();
        $this->assertSame(EventStatus::Accepting, $event->fresh()->status);

        foreach ([$owner, $leader, $buyer] as $participant) {
            $this->actingAs($participant)->post(route('events.join', $event))->assertRedirect();
        }
        $this->assertSame(3, $event->fresh()->participants()->count());

        /* ---------------------------------------------------------- */
        /* 7. サークルと商品を登録（重複検知も確認） */
        /* ---------------------------------------------------------- */
        $this->actingAs($buyer)->post(route('circles.store', $event), [
            'display_name' => '夏空スタジオ',
            'booth' => '東1 ア-12a',
        ])->assertRedirect();

        // 同名は重複として弾かれる
        $this->actingAs($buyer)
            ->post(route('circles.store', $event), ['display_name' => 'なつぞらスタジオ'])
            ->assertRedirect();
        $this->actingAs($buyer)
            ->post(route('circles.store', $event), ['display_name' => '夏空 スタジオ'])
            ->assertSessionHasErrors('display_name');

        $circle = EventCircle::firstWhere('display_name', '夏空スタジオ');
        $this->actingAs($buyer)->post(route('products.store', $circle), ['name' => '新刊イラスト集', 'price' => 1000]);
        $product = EventProduct::firstWhere('name', '新刊イラスト集');

        /* ---------------------------------------------------------- */
        /* 8. 購入希望の登録（owner 2点 / leader 1点） */
        /* ---------------------------------------------------------- */
        $this->actingAs($owner)->patch(route('purchases.personal.update', $event), [
            'quantities' => [$product->id => 2],
        ])->assertRedirect();
        $this->actingAs($leader)->patch(route('purchases.personal.update', $event), [
            'quantities' => [$product->id => 1],
        ])->assertRedirect();

        /* ---------------------------------------------------------- */
        /* 9. 共同購入リストを集計し、buyer が担当に立候補・確定 */
        /* ---------------------------------------------------------- */
        $this->actingAs($leader)->post(route('purchases.shared.sync', $event))->assertRedirect();

        $sharedPurchase = SharedPurchase::first();
        $this->assertSame(3, SharedPurchaseItem::first()->planned_quantity);

        $this->actingAs($buyer)->post(route('purchases.assignees.volunteer', $sharedPurchase))->assertRedirect();
        $this->actingAs($leader)->post(route('purchases.assignees.assign', [$sharedPurchase, $buyer]))->assertRedirect();
        $this->assertTrue($sharedPurchase->fresh()->hasConfirmedAssignee());

        /* ---------------------------------------------------------- */
        /* 10. 確定（承認フロー：最高責任者の承認で即時可決） */
        /* ---------------------------------------------------------- */
        $this->actingAs($owner)->post(route('events.advance', $event))->assertRedirect();

        $this->assertSame(EventStatus::Fixed, $event->fresh()->status);
        $this->assertNotNull($event->fresh()->fixed_at);
        $this->assertSame(1, Approval::count());

        // 確定後は購入希望を変更できない
        $this->actingAs($owner)
            ->patch(route('purchases.personal.update', $event->fresh()), ['quantities' => [$product->id => 5]])
            ->assertForbidden();

        /* ---------------------------------------------------------- */
        /* 11. 開催中にして購入結果を登録（3点希望に対し2点しか買えず） */
        /* ---------------------------------------------------------- */
        $this->actingAs($leader)->post(route('events.advance', $event->fresh()))->assertRedirect();
        $this->assertSame(EventStatus::Ongoing, $event->fresh()->status);

        $item = SharedPurchaseItem::first();
        $this->actingAs($buyer)->post(route('results.store', $item), [
            'purchased_quantity' => 2,
            'shortages' => [$owner->id => 1],
        ])->assertRedirect();

        /* ---------------------------------------------------------- */
        /* 12. 精算開始（精算リストが自動生成される） */
        /* ---------------------------------------------------------- */
        $this->actingAs($leader)->post(route('events.advance', $event->fresh()))->assertRedirect();
        $this->assertSame(EventStatus::Settling, $event->fresh()->status);

        // owner 1点(1000円) / leader 1点(1000円) を buyer が立て替えた
        $settlements = Settlement::with(['payer', 'payee'])->get();
        $this->assertCount(2, $settlements);
        $this->assertSame(2000, (int) $settlements->sum('amount'));
        $this->assertTrue($settlements->every(fn ($s) => $s->payee_user_id === $buyer->id));

        /* ---------------------------------------------------------- */
        /* 13. 支払い報告 → 受取確認 */
        /* ---------------------------------------------------------- */
        foreach ($settlements as $settlement) {
            $this->actingAs($settlement->payer)->post(route('settlements.report', $settlement))->assertRedirect();
            $payment = Payment::where('settlement_id', $settlement->id)->latest('id')->first();
            $this->assertSame(PaymentStatus::Reported, $payment->status);

            $this->actingAs($buyer)->post(route('payments.confirm', $payment))->assertRedirect();
            $this->assertSame(SettlementStatus::Completed, $settlement->fresh()->status);
        }

        /* ---------------------------------------------------------- */
        /* 14. イベント完了（最高責任者のみ） */
        /* ---------------------------------------------------------- */
        $this->actingAs($leader)->post(route('events.advance', $event->fresh()))->assertForbidden();
        $this->actingAs($owner)->post(route('events.advance', $event->fresh()))->assertRedirect();

        $this->assertSame(EventStatus::Completed, $event->fresh()->status);

        /* ---------------------------------------------------------- */
        /* 15. 完了後は最高責任者以外は閲覧のみ */
        /* ---------------------------------------------------------- */
        $this->actingAs($leader)->get(route('events.show', $event))->assertOk();
        $this->actingAs($buyer)->get(route('settlements.index', $event))->assertOk();

        $this->actingAs($leader)->get(route('events.edit', $event))->assertForbidden();
        $this->actingAs($leader)->post(route('events.advance', $event))->assertForbidden();
        $this->actingAs($leader)->post(route('events.revert', $event))->assertForbidden();
        $this->actingAs($buyer)->post(route('circles.store', $event), ['display_name' => '新規'])->assertForbidden();

        // 最高責任者は再オープンを申請できる
        $this->actingAs($owner)->post(route('events.revert', $event))->assertRedirect();
        $this->assertSame(EventStatus::Settling, $event->fresh()->status);
    }
}
