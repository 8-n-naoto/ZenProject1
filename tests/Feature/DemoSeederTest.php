<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_a_usable_dataset(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(4, User::count());
        $this->assertSame(1, Group::count());
        $this->assertSame(3, Event::count());

        $upcoming = Event::firstWhere('name', 'コミックマーケット105');
        $past = Event::firstWhere('name', 'コミックマーケット104');

        $this->assertSame(EventStatus::Accepting, $upcoming->status);
        $this->assertSame(EventStatus::Completed, $past->status);
        $this->assertSame(3, $upcoming->eventCircles()->count());
        $this->assertGreaterThan(0, $upcoming->sharedPurchases()->count());
        $this->assertTrue($past->settlements->every(fn (Settlement $s) => $s->isCompleted()));

        // 当日画面の残高表示を確認できるよう、参加者に予算を入れている
        $this->assertNotNull(
            app(\App\Services\BudgetService::class)->budgetOf($upcoming, User::firstWhere('user_id', 'owner001'))
        );

        // 未精算のまとめを確認できるよう、精算待ちのイベントを1つ残している
        $settling = Event::firstWhere('name', 'コミックマーケット103');
        $this->assertSame(EventStatus::Settling, $settling->status);
        $this->assertTrue($settling->settlements->every(fn (Settlement $s) => ! $s->isCompleted()));
        $this->assertGreaterThan(0, $settling->settlements()->count());
    }

    public function test_demo_seeder_is_idempotent(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(3, Event::count());
        $this->assertSame(4, User::count());
    }

    public function test_demo_users_can_log_in(): void
    {
        $this->seed(DemoSeeder::class);

        $this->post(route('login.store'), ['user_id' => 'owner001', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }
}
