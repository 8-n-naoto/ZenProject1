<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * クロージャの use 漏れ検出スクリプトが、実際に問題を見つけられることを確認する。
 * （過去に2回、この種の不具合を混入させたため）
 */
class ClosureLintTest extends TestCase
{
    private function lint(string $path): array
    {
        $script = base_path('tools/lint-closures.php');
        exec('php '.escapeshellarg($script).' '.escapeshellarg($path).' 2>&1', $output, $status);

        return ['status' => $status, 'output' => implode("\n", $output)];
    }

    public function test_application_code_has_no_missing_use_clauses(): void
    {
        $result = $this->lint(base_path('app'));

        $this->assertSame(0, $result['status'], $result['output']);
    }

    public function test_the_linter_detects_a_missing_use_clause(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'lint').'.php';
        file_put_contents($file, <<<'PHP'
        <?php
        class Sample {
            public function run($a, $b) {
                return collect()->each(function () use ($a) {
                    return $a . $b;
                });
            }
        }
        PHP);

        $result = $this->lint($file);
        @unlink($file);

        $this->assertSame(1, $result['status']);
        $this->assertStringContainsString('$b', $result['output']);
    }
}
