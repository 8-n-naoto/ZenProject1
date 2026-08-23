<?php

namespace Tests\Feature\Console;

use App\Enums\EventStatus;
use App\Models\Notification;
use App\Models\SharedPurchase;
use App\Services\PurchaseListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class AdvanceScheduledEventsTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_started_events_become_ongoing(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Fixed, $members);
        $event->update(['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(5)]);

        $this->artisan('events:advance-scheduled')->assertExitCode(0);

        $this->assertSame(EventStatus::Ongoing, $event->fresh()->status);
    }

    public function test_future_events_are_left_alone(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Fixed, $members);
        $event->update(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(4)]);

        $this->artisan('events:advance-scheduled')->assertExitCode(0);

        $this->assertSame(EventStatus::Fixed, $event->fresh()->status);
    }

    public function test_dry_run_changes_nothing(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Fixed, $members);
        $event->update(['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(5)]);

        $this->artisan('events:advance-scheduled --dry-run')->assertExitCode(0);

        $this->assertSame(EventStatus::Fixed, $event->fresh()->status);
    }

    public function test_assignees_are_reminded_about_unrecorded_results(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = array_merge($responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);
        ['products' => $products] = $this->makeCatalog($event);

        $purchases = app(PurchaseListService::class);
        $purchases->savePersonalPurchases($event, $members[1], [$products[0]->id => 1]);
        $purchases->syncAll($event, $responsibles[0]);
        $purchases->assign(SharedPurchase::first(), $members[0], $responsibles[0]);

        $event->update([
            'status' => EventStatus::Ongoing,
            'fixed_at' => now(),
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $this->artisan('events:advance-scheduled')->assertExitCode(0);

        $this->assertSame(
            1,
            Notification::where('user_id', $members[0]->id)->where('type', 'result.reminder')->count()
        );
        $this->assertSame(
            0,
            Notification::where('user_id', $members[1]->id)->where('type', 'result.reminder')->count()
        );
    }

    public function test_result_reminders_are_not_repeated_within_a_day(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = array_merge($responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);
        ['products' => $products] = $this->makeCatalog($event);

        $purchases = app(PurchaseListService::class);
        $purchases->savePersonalPurchases($event, $members[1], [$products[0]->id => 1]);
        $purchases->syncAll($event, $responsibles[0]);
        $purchases->assign(SharedPurchase::first(), $members[0], $responsibles[0]);

        $event->update([
            'status' => EventStatus::Ongoing,
            'fixed_at' => now(),
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        // 1日に3回まわしても通知は1件
        $this->artisan('events:advance-scheduled')->assertExitCode(0);
        $this->artisan('events:advance-scheduled')->assertExitCode(0);
        $this->artisan('events:advance-scheduled')->assertExitCode(0);

        $this->assertSame(
            1,
            Notification::where('user_id', $members[0]->id)->where('type', 'result.reminder')->count()
        );
    }

    public function test_no_reminder_when_results_are_recorded(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = array_merge($responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);
        ['products' => $products] = $this->makeCatalog($event);

        $purchases = app(PurchaseListService::class);
        $purchases->savePersonalPurchases($event, $members[1], [$products[0]->id => 1]);
        $purchases->syncAll($event, $responsibles[0]);
        $shared = SharedPurchase::first();
        $purchases->assign($shared, $members[0], $responsibles[0]);

        $event->update([
            'status' => EventStatus::Ongoing,
            'fixed_at' => now(),
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        app(\App\Services\PurchaseResultService::class)->recordForSharedItem(
            $shared->items()->first(),
            $members[0],
            1,
            null
        );

        $this->artisan('events:advance-scheduled')->assertExitCode(0);

        $this->assertSame(0, Notification::where('type', 'result.reminder')->count());
    }

    public function test_participants_without_wishes_are_reminded_before_the_event(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup(['member' => 2]);
        $participants = array_merge($responsibles, $members);
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $participants);
        ['products' => $products] = $this->makeCatalog($event);
        $event->update(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);

        app(PurchaseListService::class)->savePersonalPurchases($event, $members[0], [$products[0]->id => 1]);

        $this->artisan('events:advance-scheduled')->assertExitCode(0);

        $this->assertSame(0, Notification::where('user_id', $members[0]->id)->where('type', 'wish.reminder')->count());
        $this->assertSame(1, Notification::where('user_id', $members[1]->id)->where('type', 'wish.reminder')->count());

        // 同じ日に二重で送らない
        $this->artisan('events:advance-scheduled')->assertExitCode(0);
        $this->assertSame(1, Notification::where('user_id', $members[1]->id)->where('type', 'wish.reminder')->count());
    }

    public function test_no_wish_reminder_for_distant_events(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $members);
        $event->update(['starts_at' => now()->addDays(20), 'ends_at' => now()->addDays(21)]);

        $this->artisan('events:advance-scheduled')->assertExitCode(0);

        $this->assertSame(0, Notification::where('type', 'wish.reminder')->count());
    }
}
