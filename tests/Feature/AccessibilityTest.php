<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

/**
 * 支援技術で使えるようにするための最低限の確認。
 */
class AccessibilityTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_every_input_has_a_label(): void
    {
        ['group' => $group, 'responsible' => $responsibles, 'member' => $members] = $this->makeGroup();
        $event = $this->makeEvent($group, $responsibles[0], EventStatus::Accepting, $members);
        $this->makeCatalog($event);

        $urls = [
            route('groups.create'),
            route('groups.edit', $group),
            route('events.create', $group),
            route('circles.create', $event),
            route('circles.index', $event),
            route('circles.bulk.form', $event),
            route('profile.edit'),
            route('purchases.personal.index', $event),
            route('purchases.shared.index', $event),
        ];

        foreach ($urls as $url) {
            $html = $this->actingAs($responsibles[0])->get($url)->assertOk()->getContent();

            preg_match_all('/<(input|textarea|select)\b[^>]*>/i', $html, $matches);

            foreach ($matches[0] as $tag) {
                if (preg_match('/type="(hidden|submit|checkbox|radio|file)"/i', $tag)) {
                    continue;
                }

                if (preg_match('/aria-label=/i', $tag)) {
                    continue;
                }

                $this->assertMatchesRegularExpression(
                    '/\bid="([^"]+)"/',
                    $tag,
                    'id が無い入力欄があります: '.$tag.' ('.$url.')'
                );

                preg_match('/\bid="([^"]+)"/', $tag, $idMatch);
                $id = $idMatch[1];

                $this->assertStringContainsString(
                    'for="'.$id.'"',
                    $html,
                    'ラベルが無い入力欄があります: id='.$id.' ('.$url.')'
                );
            }
        }
    }

    public function test_guest_forms_have_labels(): void
    {
        foreach ([route('login'), route('register'), route('password.request')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            preg_match_all('/<(input|textarea|select)\b[^>]*>/i', $html, $matches);

            foreach ($matches[0] as $tag) {
                if (preg_match('/type="(hidden|submit|checkbox|radio|file)"/i', $tag) || preg_match('/aria-label=/i', $tag)) {
                    continue;
                }

                preg_match('/\bid="([^"]+)"/', $tag, $idMatch);
                $this->assertNotEmpty($idMatch, 'id が無い入力欄があります: '.$tag);
                $this->assertStringContainsString('for="'.$idMatch[1].'"', $html, $url);
            }
        }
    }

    public function test_validation_errors_are_announced(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->from(route('groups.create'))
            ->post(route('groups.store'), ['name' => ''])
            ->assertRedirect(route('groups.create'))
            ->getTargetUrl();

        $page = $this->actingAs($user)->get($html)->assertOk()->getContent();

        $this->assertStringContainsString('role="alert"', $page);
        $this->assertStringContainsString('aria-invalid="true"', $page);
        $this->assertStringContainsString('aria-describedby="name-error"', $page);
    }

    public function test_pages_declare_language_and_skip_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            // html 要素には見た目の指定（data-theme）も付くため、属性の前方一致で見る
            ->assertSee('<html lang="ja"', false)
            ->assertSee('本文へスキップ');
    }

    public function test_current_navigation_item_is_marked(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-current="page"', false);
    }
}
