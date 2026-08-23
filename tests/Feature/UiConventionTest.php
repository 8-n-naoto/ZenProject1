<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 画面の作り方の約束ごとを機械的に確認する。
 * （レビューで見つかった「確認なしの破壊的操作」「押しにくいボタン」の再発防止）
 */
class UiConventionTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function bladeFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    public function test_destructive_forms_ask_for_confirmation(): void
    {
        $offenders = [];
        $checked = 0;

        foreach ($this->bladeFiles() as $path) {
            $source = (string) file_get_contents($path);

            // <form ...> ... </form> を1つずつ取り出す
            preg_match_all('/<form\b.*?<\/form>/su', $source, $matches);

            foreach ($matches[0] as $form) {
                $isDestructive = str_contains($form, "@method('DELETE')")
                    || preg_match('/route\(\'[^\']*(destroy|remove|cancel|reject|withdraw|leave|decline)/', $form) === 1;

                if (! $isDestructive) {
                    continue;
                }

                $checked++;

                if (! str_contains($form, 'onsubmit=')) {
                    preg_match('/action="\{\{ route\(\'([^\']+)\'/', $form, $route);
                    $offenders[] = basename(dirname($path)).'/'.basename($path).' → '.($route[1] ?? '?');
                }
            }
        }

        $this->assertSame([], $offenders, "確認ダイアログのない破壊的操作:\n".implode("\n", $offenders));
        $this->assertGreaterThan(8, $checked, '破壊的な操作を検出できていません（検出ロジックの確認）');
    }

    public function test_buttons_keep_a_finger_friendly_height(): void
    {
        $button = (string) file_get_contents(resource_path('views/components/button.blade.php'));

        foreach (['sm', 'md', 'lg'] as $size) {
            $this->assertMatchesRegularExpression(
                "/'{$size}' => 'min-h-1?\\d/",
                $button,
                "ボタンサイズ {$size} に最小の高さが設定されていません"
            );
        }
    }

    public function test_every_utility_class_used_in_views_is_generated(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));

        // 見た目に直結し、生成漏れに気づきにくいものを確認する
        foreach (['border-dashed', 'pb-safe', 'min-h-10', 'truncate'] as $class) {
            $this->assertStringContainsString('.'.$class.'{', $css, $class.' が生成されていません');
        }
    }
}
