<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_forbidden_page_is_localised(): void
    {
        ['group' => $group] = $this->makeGroup();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('groups.show', $group))
            ->assertForbidden()
            ->assertSee('この操作は許可されていません')
            ->assertSee('403');
    }

    public function test_not_found_page_is_localised(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/groups/999999')
            ->assertNotFound()
            ->assertSee('ページが見つかりません');
    }

    public function test_error_pages_link_home_for_authenticated_users(): void
    {
        ['group' => $group] = $this->makeGroup();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('groups.show', $group))
            ->assertSee('ホームへ戻る');
    }
}
