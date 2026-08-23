<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_notifications_are_paginated(): void
    {
        $user = User::factory()->create();

        $rows = [];
        for ($i = 0; $i < 45; $i++) {
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

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('グループ0')
            ->assertDontSee('グループ40')
            ->assertSee('次へ');

        $this->actingAs($user)
            ->get(route('notifications.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('グループ40')
            ->assertSee('前へ');

        $this->assertSame(45, Notification::count());
    }

    public function test_history_screen_paginates(): void
    {
        ['group' => $group, 'highest' => $highests, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], \App\Enums\EventStatus::Preparation, $members);

        $history = app(\App\Services\ChangeHistoryService::class);
        for ($i = 0; $i < 45; $i++) {
            $history->record($highests[0], $event, 'event.updated', [], $group, $event);
        }

        $this->actingAs($highests[0])
            ->get(route('histories.index', $event))
            ->assertOk()
            ->assertSee('次へ');
    }
}
