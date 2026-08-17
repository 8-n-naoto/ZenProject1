<?php

namespace Tests\Feature;

use App\Models\Memo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_saved_memos(): void
    {
        $memo = Memo::factory()->create(['memo' => '表示するメモ']);

        $this->get('/')->assertOk()->assertSee($memo->memo);
    }

    public function test_memo_can_be_created(): void
    {
        $response = $this->post(route('create'), ['memo' => '新しいメモ']);

        $response->assertRedirect(route('top'));
        $response->assertSessionHas('status', 'メモを作成しました。');
        $this->assertDatabaseHas('memos', ['memo' => '新しいメモ']);
    }

    public function test_memo_is_required_when_creating(): void
    {
        $response = $this->from(route('store'))->post(route('create'), ['memo' => '']);

        $response->assertRedirect(route('store'));
        $response->assertSessionHasErrors('memo');
        $this->assertDatabaseCount('memos', 0);
    }

    public function test_memo_can_be_updated(): void
    {
        $memo = Memo::factory()->create(['memo' => '更新前']);

        $response = $this->patch(route('update', $memo), ['memo' => '更新後']);

        $response->assertRedirect(route('show', $memo));
        $response->assertSessionHas('status', 'メモを更新しました。');
        $this->assertDatabaseHas('memos', ['id' => $memo->id, 'memo' => '更新後']);
    }

    public function test_memo_can_be_deleted(): void
    {
        $memo = Memo::factory()->create();

        $response = $this->delete(route('destroy', $memo));

        $response->assertRedirect(route('top'));
        $response->assertSessionHas('status', 'メモを削除しました。');
        $this->assertDatabaseMissing('memos', ['id' => $memo->id]);
    }
}
