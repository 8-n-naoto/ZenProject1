<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\SharedPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_invitation_creates_a_notification(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $target = User::factory()->create();

        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $target]));

        $notification = Notification::where('user_id', $target->id)->first();

        $this->assertNotNull($notification);
        $this->assertSame('invitation.received', $notification->type);
        $this->assertStringContainsString($group->name, $notification->message());
        $this->assertTrue($notification->isUnread());
    }

    public function test_status_change_notifies_participants(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation, $members);

        $this->actingAs($highests[0])->post(route('events.advance', $event));

        $this->assertSame(1, Notification::where('user_id', $members[0]->id)->where('type', 'event.accepting')->count());
    }

    public function test_assignee_is_notified_when_confirmed(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $participants = array_merge($responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);
        ['products' => $products] = $this->makeCatalog($event);

        $this->actingAs($members[0])->patch(route('purchases.personal.update', $event), [
            'quantities' => [$products[0]->id => 1],
        ]);
        $this->actingAs($responsibles[0])->post(route('purchases.shared.sync', $event));
        $sharedPurchase = SharedPurchase::first();

        $this->actingAs($responsibles[0])->post(route('purchases.assignees.assign', [$sharedPurchase, $members[0]]));

        $this->assertSame(
            1,
            Notification::where('user_id', $members[0]->id)->where('type', 'assignee.confirmed')->count()
        );
    }

    public function test_payment_report_and_confirmation_notify_the_other_party(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = [$responsibles[0], $members[0], $members[1]];
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Preparation, $participants);

        $event = $this->runEventToSettlement($event, $participants, [
            ['circle' => 'サークルX', 'product' => '新刊', 'price' => 1000, 'wishes' => [0 => 1], 'assignee' => 1],
        ]);

        $settlement = Settlement::with(['payer', 'payee'])->first();
        $payer = $settlement->payer;
        $payee = $settlement->payee;

        $this->actingAs($payer)->post(route('settlements.report', $settlement));
        $this->assertSame(1, Notification::where('user_id', $payee->id)->where('type', 'payment.reported')->count());

        $this->actingAs($payee)->post(route('payments.confirm', Payment::first()));
        $this->assertSame(1, Notification::where('user_id', $payer->id)->where('type', 'payment.confirmed')->count());
    }

    public function test_notifications_can_be_marked_as_read(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $target = User::factory()->create();
        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $target]));

        $this->actingAs($target)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, Notification::where('user_id', $target->id)->unread()->count());
    }

    public function test_notification_screen_renders(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $target = User::factory()->create();
        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $target]));

        $this->actingAs($target)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($group->name);
    }

    public function test_users_only_see_their_own_notifications(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $target = User::factory()->create();
        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $target]));

        $this->actingAs($members[0])
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('お知らせはありません');
    }

    public function test_unread_filter_works(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $target = User::factory()->create();
        $this->actingAs($highests[0])->post(route('groups.invite', [$group, $target]));

        $this->actingAs($target)
            ->get(route('notifications.index', ['unread' => 1]))
            ->assertOk()
            ->assertSee($group->name);

        $this->actingAs($target)->post(route('notifications.read-all'));

        $this->actingAs($target)
            ->get(route('notifications.index', ['unread' => 1]))
            ->assertOk()
            ->assertSee('未読のお知らせはありません');

        $this->actingAs($target)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($group->name);
    }
}
