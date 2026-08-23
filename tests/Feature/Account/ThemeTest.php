<?php

namespace Tests\Feature\Account;

use App\Enums\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_gets_the_default_theme(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->theme);
        $this->assertSame(Theme::default(), $user->preferredTheme());

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-theme="'.Theme::default()->value.'"', false);
    }

    public function test_theme_can_be_changed_and_is_applied_to_every_screen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.theme.update'), ['theme' => Theme::Venue->value])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame(Theme::Venue->value, $user->fresh()->theme);

        $this->actingAs($user->fresh())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-theme="venue"', false)
            ->assertSee(Theme::Venue->themeColor(), false);
    }

    public function test_unknown_theme_is_rejected(): void
    {
        $user = User::factory()->create(['theme' => Theme::Editorial->value]);

        $this->actingAs($user)
            ->put(route('profile.theme.update'), ['theme' => 'neon'])
            ->assertSessionHasErrors('theme');

        $this->assertSame(Theme::Editorial->value, $user->fresh()->theme);
    }

    public function test_broken_stored_value_falls_back_to_the_default(): void
    {
        // 手作業でのDB編集や、テーマを廃止したあとの残骸で画面が落ちないこと
        $user = User::factory()->create();
        $user->forceFill(['theme' => 'removed-theme'])->save();

        $this->assertSame(Theme::default(), $user->fresh()->preferredTheme());

        $this->actingAs($user->fresh())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-theme="'.Theme::default()->value.'"', false);
    }

    public function test_guest_screens_use_the_default_theme(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-theme="'.Theme::default()->value.'"', false);
    }

    public function test_settings_screen_lists_every_theme(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.edit'))->assertOk();

        foreach (Theme::options() as $theme) {
            $response->assertSee('value="'.$theme->value.'"', false);
            $response->assertSee($theme->label());
        }
    }

    public function test_every_theme_declares_its_own_block_in_the_stylesheet(): void
    {
        $css = file_get_contents(public_path('css/theme.css'));

        $this->assertNotFalse($css);

        foreach (Theme::options() as $theme) {
            $this->assertStringContainsString(
                'html[data-theme="'.$theme->value.'"]',
                $css,
                $theme->value.' のテーマがCSSに定義されていない'
            );
        }
    }

    public function test_theme_stylesheet_in_public_matches_its_source(): void
    {
        // php tools/build-css.php の複写を忘れたまま公開するのを防ぐ
        $this->assertSame(
            file_get_contents(resource_path('css/theme.css')),
            file_get_contents(public_path('css/theme.css')),
            'resources/css/theme.css を変更したら php tools/build-css.php を実行すること'
        );
    }
}
