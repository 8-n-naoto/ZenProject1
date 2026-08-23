<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 通知・変更履歴のメッセージが「キーのまま」表示されないことを確認する。
 *
 * サービス側で使っているキーと、モデル側の日本語文の対応漏れを機械的に検出する。
 */
class MessageKeyCoverageTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function keysUsedIn(string $pattern): array
    {
        $keys = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            preg_match_all($pattern, (string) file_get_contents($file->getPathname()), $matches);
            $keys = array_merge($keys, $matches[1]);
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<int, string>
     */
    private function armsOf(string $file, string $method): array
    {
        $source = (string) file_get_contents($file);
        $start = strpos($source, 'function '.$method);
        $this->assertNotFalse($start, $method.' が見つかりません');

        preg_match_all("/'([a-z]+\.[a-z_]+)' =>/", substr($source, $start), $matches);

        return array_values(array_unique($matches[1]));
    }

    public function test_every_notification_type_has_japanese_text(): void
    {
        $used = $this->keysUsedIn("/notify\(\s*[^,]+,\s*'([a-z]+\.[a-z_]+)'/s");
        $used = array_merge($used, $this->keysUsedIn("/notifyParticipants\(\s*[^,]+,\s*'([a-z]+\.[a-z_]+)'/s"));
        $used = array_values(array_unique($used));

        $this->assertNotEmpty($used, '通知キーを検出できていません');

        $arms = $this->armsOf(app_path('Models/Notification.php'), 'message');
        $missing = array_values(array_diff($used, $arms));

        $this->assertSame([], $missing, '日本語文がない通知キー: '.implode(', ', $missing));
    }

    public function test_every_history_action_has_japanese_text(): void
    {
        $used = $this->keysUsedIn("/->record\(\s*[^,]+,\s*[^,]+,\s*'([a-z]+\.[a-z_]+)'/s");

        $this->assertNotEmpty($used, '変更履歴のキーを検出できていません');

        $arms = $this->armsOf(app_path('Models/ChangeHistory.php'), 'description');
        $missing = array_values(array_diff($used, $arms));

        $this->assertSame([], $missing, '日本語文がない変更履歴キー: '.implode(', ', $missing));
    }
}
