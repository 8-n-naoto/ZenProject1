<?php

namespace Tests\Feature\Security;

use App\Enums\EventStatus;
use App\Enums\GroupRole;
use App\Models\Payment;
use App\Models\PurchaseResult;
use App\Models\Settlement;
use App\Models\SharedPurchase;
use App\Models\SharedPurchaseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 認可レビューで見つかった抜け道が再発しないことを検証する。
 */
class AuthorizationRegressionTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    /**
     * 受付中イベント（参加者: 責任者・一般メンバー2人）とサークル・商品。
     */
    private function acceptingEvent(): array
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = array_merge($highests, $responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);
        ['circle' => $circle, 'products' => $products] = $this->makeCatalog($event);

        return compact('group', 'highests', 'responsibles', 'members', 'event', 'circle', 'products');
    }

    public function test_removed_member_cannot_update_purchase_wishes(): void
    {
        ['group' => $group, 'event' => $event, 'members' => $members, 'products' => $products] = $this->acceptingEvent();
        $this->markAsLeft($group, $members[0]);

        $this->actingAs($members[0])
            ->patch(route('purchases.personal.update', $event), ['quantities' => [$products[0]->id => 10]])
            ->assertForbidden();

        $this->assertSame(0, \App\Models\PersonalPurchase::count());
    }

    public function test_removed_member_cannot_volunteer_or_withdraw(): void
    {
        ['group' => $group, 'event' => $event, 'responsibles' => $responsibles, 'members' => $members, 'products' => $products] = $this->acceptingEvent();

        $this->actingAs($members[0])->patch(route('purchases.personal.update', $event), ['quantities' => [$products[0]->id => 1]]);
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $sharedPurchase = SharedPurchase::first();

        $this->markAsLeft($group, $members[1]);

        $this->actingAs($members[1])
            ->post(route('purchases.assignees.volunteer', $sharedPurchase))
            ->assertForbidden();

        $this->assertSame(0, $sharedPurchase->assignees()->count());
    }

    public function test_removed_member_cannot_be_assigned_as_buyer(): void
    {
        ['group' => $group, 'event' => $event, 'responsibles' => $responsibles, 'members' => $members, 'products' => $products] = $this->acceptingEvent();

        $this->actingAs($members[0])->patch(route('purchases.personal.update', $event), ['quantities' => [$products[0]->id => 1]]);
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $sharedPurchase = SharedPurchase::first();

        $this->markAsLeft($group, $members[1]);

        $this->actingAs($responsibles[0])
            ->post(route('purchases.assignees.assign', [$sharedPurchase, $members[1]]))
            ->assertSessionHasErrors('assignee');

        $this->assertSame(0, $sharedPurchase->assignees()->count());
    }

    public function test_removed_member_cannot_leave_the_event(): void
    {
        ['group' => $group, 'event' => $event, 'members' => $members] = $this->acceptingEvent();
        $this->markAsLeft($group, $members[0]);

        $this->actingAs($members[0])
            ->delete(route('events.leave', $event))
            ->assertForbidden();

        $this->assertTrue($event->fresh()->isParticipant($members[0]));
    }

    public function test_excess_takeover_must_be_a_participant(): void
    {
        ['event' => $event, 'responsibles' => $responsibles, 'members' => $members, 'products' => $products] = $this->acceptingEvent();

        $this->actingAs($members[0])->patch(route('purchases.personal.update', $event), ['quantities' => [$products[0]->id => 1]]);
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $sharedPurchase = SharedPurchase::first();
        $this->actingAs($responsibles[0])->post(route('purchases.assignees.assign', [$sharedPurchase, $members[0]]));

        $event->update(['status' => EventStatus::Ongoing, 'fixed_at' => now()]);
        $outsider = User::factory()->create();
        $item = SharedPurchaseItem::first();

        $this->actingAs($members[0])
            ->post(route('results.store', $item), [
                'purchased_quantity' => 4,
                'excess_user_id' => $outsider->id,
            ])
            ->assertSessionHasErrors('excess_user_id');

        $this->assertSame(0, PurchaseResult::count());
        $this->assertSame(0, \App\Models\ExcessTakeover::count());
    }

    public function test_only_the_payee_can_confirm_a_payment(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = [$responsibles[0], $members[0], $members[1]];
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Preparation, $participants);
        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => 'X', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1], 'assignee' => 1],
        ]);

        $settlement = Settlement::with(['payer', 'payee'])->first();
        $this->actingAs($settlement->payer)->post(route('settlements.report', $settlement));
        $payment = Payment::first();

        // 責任者であっても当事者でなければ受取確認できない
        $highest = $group->activeMembers()->wherePivot('role', GroupRole::HighestResponsible->value)->first();
        $this->actingAs($highest)->post(route('payments.confirm', $payment))->assertForbidden();

        $this->actingAs($settlement->payee)->post(route('payments.confirm', $payment))->assertRedirect();
    }

    public function test_removed_member_cannot_report_or_confirm_payment(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = [$responsibles[0], $members[0], $members[1]];
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Preparation, $participants);
        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => 'X', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1], 'assignee' => 1],
        ]);

        $settlement = Settlement::with(['payer', 'payee'])->first();
        $this->markAsLeft($group, $settlement->payer);

        $this->actingAs($settlement->payer)
            ->post(route('settlements.report', $settlement))
            ->assertForbidden();

        $this->assertSame(0, Payment::count());
    }

    public function test_relock_is_forbidden_on_a_completed_event(): void
    {
        ['group' => $group, 'highests' => $highests, 'event' => $event] = $this->acceptingEvent();
        $event->update(['status' => EventStatus::Completed, 'fixed_at' => now()]);

        $this->actingAs($highests[0])
            ->post(route('approvals.relock', $event->fresh()))
            ->assertForbidden();
    }

    public function test_last_responsible_cannot_be_promoted_away(): void
    {
        // 昇格でも「責任者が0人」になる変更は認めない（完成定義書 1章）
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles] = $this->makeGroup();

        $this->actingAs($highests[0])
            ->patch(route('groups.members.role.update', [$group, $responsibles[0]]), [
                'role' => GroupRole::HighestResponsible->value,
            ])
            ->assertSessionHasErrors('member');

        $this->assertSame(GroupRole::Responsible, $group->fresh()->roleOf($responsibles[0]));
    }

    public function test_a_responsible_can_be_promoted_once_a_replacement_exists(): void
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles, 'member' => $members]
            = $this->makeGroup(['responsible' => 1, 'member' => 1]);

        // 先に後任の責任者を任命してから昇格させる
        $this->actingAs($highests[0])
            ->patch(route('groups.members.role.update', [$group, $members[0]]), [
                'role' => GroupRole::Responsible->value,
            ])
            ->assertRedirect();

        $this->actingAs($highests[0])
            ->patch(route('groups.members.role.update', [$group->fresh(), $responsibles[0]]), [
                'role' => GroupRole::HighestResponsible->value,
            ])
            ->assertRedirect();

        $this->assertSame(GroupRole::HighestResponsible, $group->fresh()->roleOf($responsibles[0]));
    }

    public function test_last_responsible_still_cannot_be_demoted(): void
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles] = $this->makeGroup();

        $this->actingAs($highests[0])
            ->patch(route('groups.members.role.update', [$group, $responsibles[0]]), [
                'role' => GroupRole::Member->value,
            ])
            ->assertSessionHasErrors('member');
    }

    public function test_event_information_can_be_edited_only_while_unlocked(): void
    {
        ['group' => $group, 'highests' => $highests, 'event' => $event] = $this->acceptingEvent();
        $event->update(['status' => EventStatus::Fixed, 'fixed_at' => now()]);

        $payload = [
            'name' => '変更後',
            'venue_name' => '会場',
            'days' => [['event_date' => now()->addDays(40)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '16:00']],
        ];

        $this->actingAs($highests[0])->patch(route('events.update', $event->fresh()), $payload)->assertForbidden();

        $this->actingAs($highests[0])->post(route('approvals.unlock', $event->fresh()))->assertRedirect();

        $this->actingAs($highests[0])->patch(route('events.update', $event->fresh()), $payload)->assertRedirect();
        $this->assertSame('変更後', $event->fresh()->name);
    }

    public function test_dashboard_hides_locked_events_the_user_cannot_open(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $outsider = $members[0];

        // 参加していないメンバーは、確定済以降のイベントを開けない
        $locked = $this->makeEvent($group, $highests[0], \App\Enums\EventStatus::Fixed, [$highests[0]]);

        $this->actingAs($outsider)->get(route('events.show', $locked))->assertForbidden();

        $this->actingAs($outsider)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee($locked->name);

        // 参加者には見える
        $this->actingAs($highests[0])->get(route('dashboard'))
            ->assertOk()
            ->assertSee($locked->name);
    }
}
