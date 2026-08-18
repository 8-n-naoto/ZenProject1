<?php

namespace Tests\Feature;

use App\Models\Memo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function test_index_displays_dashboard_for_verified_user(): void
    {
        $user = $this->verifiedUser();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertViewIs('dashboard')
            ->assertSee($user->name);
    }

    public function test_memo_can_be_created(): void
    {
        $user = $this->verifiedUser();

        $response = $this->actingAs($user)
            ->post(route('create'), ['memo' => '新しいメモ']);

        $response->assertRedirect(route('top'));
        $response->assertSessionHas('status', 'メモを作成しました。');
        $this->assertDatabaseHas('memos', ['memo' => '新しいメモ']);
    }

    public function test_memo_is_required_when_creating(): void
    {
        $user = $this->verifiedUser();

        $response = $this->actingAs($user)
            ->from(route('store'))
            ->post(route('create'), ['memo' => '']);

        $response->assertRedirect(route('store'));
        $response->assertSessionHasErrors('memo');
        $this->assertDatabaseCount('memos', 0);
    }

    public function test_memo_can_be_updated(): void
    {
        $user = $this->verifiedUser();
        $memo = Memo::factory()->create(['memo' => '更新前']);

        $response = $this->actingAs($user)
            ->patch(route('update', $memo), ['memo' => '更新後']);

        $response->assertRedirect(route('show', $memo));
        $response->assertSessionHas('status', 'メモを更新しました。');
        $this->assertDatabaseHas('memos', [
            'id' => $memo->id,
            'memo' => '更新後',
        ]);
    }

    public function test_memo_can_be_deleted(): void
    {
        $user = $this->verifiedUser();
        $memo = Memo::factory()->create();

        $response = $this->actingAs($user)
            ->delete(route('destroy', $memo));

        $response->assertRedirect(route('top'));
        $response->assertSessionHas('status', 'メモを削除しました。');
        $this->assertDatabaseMissing('memos', ['id' => $memo->id]);
    }
}