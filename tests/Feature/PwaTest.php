<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_valid_json_and_points_at_existing_icons(): void
    {
        $path = public_path('manifest.webmanifest');
        $this->assertFileExists($path);

        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
            $size = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame($icon['sizes'], $size[0].'x'.$size[1]);
        }
    }

    public function test_service_worker_and_offline_page_exist(): void
    {
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));

        $worker = (string) file_get_contents(public_path('sw.js'));

        // ログイン後のHTMLはキャッシュしない（他人に見えてしまうため）
        $this->assertStringContainsString("request.mode === 'navigate'", $worker);
        $this->assertStringContainsString("caches.match('/offline.html')", $worker);
        $this->assertStringNotContainsString('cache.put(request, response)', $worker);
    }

    public function test_service_worker_precaches_files_that_exist(): void
    {
        $worker = (string) file_get_contents(public_path('sw.js'));

        preg_match('/var ASSETS = \[(.*?)\];/s', $worker, $matches);
        $this->assertNotEmpty($matches, 'ASSETS の定義が見つかりません');

        preg_match_all("/'([^']+)'/", $matches[1], $paths);

        foreach ($paths[1] as $path) {
            $this->assertFileExists(public_path(ltrim($path, '/')), $path.' が存在しません');
        }
    }

    /**
     * プリキャッシュの中身とキャッシュ名がずれていないこと。
     *
     * ずれていると、CSSやオフライン案内を更新しても
     * 一度アクセスした利用者には古いものが返り続ける。
     * （`php tools/build-css.php` で自動的に更新される）
     */
    public function test_service_worker_cache_name_matches_its_assets(): void
    {
        $worker = (string) file_get_contents(public_path('sw.js'));

        preg_match('/var ASSETS = \[(.*?)\];/s', $worker, $assetBlock);
        $this->assertNotEmpty($assetBlock, 'ASSETS の定義が見つかりません');

        preg_match_all("/'([^']+)'/", $assetBlock[1], $paths);

        $material = '';

        foreach ($paths[1] as $assetPath) {
            $absolute = public_path(ltrim($assetPath, '/'));
            $material .= $assetPath.':'.(is_file($absolute) ? sha1_file($absolute) : 'missing').'|';
        }

        $expected = 'kyodo-static-'.substr(sha1($material), 0, 12);

        preg_match("/var CACHE = '([^']+)';/", $worker, $cacheName);
        $this->assertNotEmpty($cacheName, 'CACHE の定義が見つかりません');

        $this->assertSame(
            $expected,
            $cacheName[1],
            'キャッシュ名が中身と一致していません。php tools/build-css.php を実行してください'
        );
    }

    public function test_pages_reference_the_manifest(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('name="theme-color"', false);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee("navigator.serviceWorker.register('/sw.js')", false);
    }
}
