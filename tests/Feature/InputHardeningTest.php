<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\InvitationStatus;
use App\Models\Notification;
use App\Models\User;
use App\Services\ImageStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 入力の想定外な値でも壊れない・情報が漏れないことを確認する。
 */
class InputHardeningTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_search_wildcards_are_escaped(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation);
        $this->makeCatalog($event, '夏空スタジオ', [['name' => '新刊', 'price' => 1000]]);

        // 「_」「%」がワイルドカードとして働くと全件が出てしまう
        foreach (['_', '%', '__'] as $keyword) {
            $this->actingAs($highests[0])
                ->get(route('circles.index', [$event, 'q' => $keyword]))
                ->assertOk()
                ->assertDontSee('夏空スタジオ');
        }

        $this->actingAs($highests[0])
            ->get(route('circles.index', [$event, 'q' => '夏空']))
            ->assertOk()
            ->assertSee('夏空スタジオ');
    }

    public function test_user_search_does_not_allow_enumeration(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $victim = User::factory()->create(['user_id' => 'victim01', 'name' => '山田 太郎']);

        foreach (['_', '%', 'a'] as $keyword) {
            $this->actingAs($highests[0])
                ->get(route('groups.search-users', [$group, 'q' => $keyword]))
                ->assertOk()
                ->assertDontSee('山田 太郎');
        }

        $this->actingAs($highests[0])
            ->get(route('groups.search-users', [$group, 'q' => 'victim01']))
            ->assertOk()
            ->assertSee('山田 太郎');
    }

    public function test_very_long_search_keywords_do_not_error(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Preparation);

        $long = str_repeat('あ', 20000);

        $this->actingAs($highests[0])->get(route('circles.index', [$event, 'q' => $long]))->assertOk();
        $this->actingAs($highests[0])->get(route('groups.search-users', [$group, 'q' => $long]))->assertOk();
    }

    public function test_malformed_days_gives_a_validation_error_not_a_crash(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();

        $this->actingAs($highests[0])
            ->post(route('events.store', $group), [
                'name' => 'テストイベント',
                'days' => 'abc',
            ])
            ->assertSessionHasErrors('days');
    }

    public function test_huge_arrays_are_rejected(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting, [$highests[0]]);

        $quantities = [];
        for ($i = 1; $i <= 3000; $i++) {
            $quantities[$i] = 1;
        }

        $this->actingAs($highests[0])
            ->patch(route('purchases.personal.update', $event), ['quantities' => $quantities])
            ->assertSessionHasErrors('quantities');
    }

    public function test_choosing_a_new_image_wins_over_the_remove_checkbox(): void
    {
        Storage::fake('public');
        $images = app(ImageStorageService::class);

        $current = $images->store(UploadedFile::fake()->image('old.png'), 'circles');

        $result = $images->sync(UploadedFile::fake()->image('new.png'), $current, 'circles', true);

        $this->assertTrue($result['changed']);
        $this->assertNotNull($result['path'], '新しい画像を選んだのに消えています');
        Storage::disk('public')->assertMissing($current);
        Storage::disk('public')->assertExists($result['path']);
    }

    public function test_invitation_history_survives_the_group_being_deleted(): void
    {
        ['group' => $group, 'highest' => $highests] = $this->makeGroup();
        $invited = User::factory()->create();

        $this->actingAs($highests[0])
            ->post(route('groups.invite', [$group, $invited]))
            ->assertRedirect();

        $this->actingAs($highests[0])->delete(route('groups.destroy', $group))->assertRedirect();

        $this->actingAs($invited)->get(route('invitations.index'))->assertOk();

        $this->assertDatabaseHas('invitations', [
            'group_id' => $group->id,
            'invited_user_id' => $invited->id,
            'status' => InvitationStatus::Cancelled->value,
        ]);
    }

    public function test_out_of_range_pages_are_handled(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        for ($i = 0; $i < 30; $i++) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'invitation.received',
                'payload' => ['group' => 'グループ'.$i],
                'notified_at' => now()->subMinutes($i),
            ]);
        }

        $this->actingAs($user)->get(route('notifications.index', ['page' => 99999]))
            ->assertOk()
            ->assertSee('このページはありません')
            ->assertDontSee('99999 /');

        $this->actingAs($user)->get(route('notifications.index', ['page' => 'abc']))->assertOk();
        $this->actingAs($user)->get(route('notifications.index', ['page' => 0]))->assertOk();
    }
}
