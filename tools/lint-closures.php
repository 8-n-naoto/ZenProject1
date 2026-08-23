<?php

/**
 * クロージャの `use` 漏れ（未定義変数）を検出する簡易静的チェック。
 *
 *   php tools/lint-closures.php [対象ディレクトリ...]
 *
 * 無名関数 `function () use (...) {}` の中で、
 * use にも引数にも入っておらず、中でも代入されていない変数を使っていると警告する。
 * アロー関数は自動的に外側を取り込むため対象外。
 */

require __DIR__.'/../vendor/autoload.php';

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

$targets = array_slice($argv, 1);

if ($targets === []) {
    $targets = [__DIR__.'/../app', __DIR__.'/../database', __DIR__.'/../routes'];
}

$parser = (new ParserFactory)->createForNewestSupportedVersion();
$finder = new NodeFinder;

/** グローバル変数・特別扱いする変数 */
$always = ['this', 'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_REQUEST', '_ENV', 'http_response_header', 'php_errormsg'];

$problems = [];

/**
 * ノード配下で「代入されている」変数名を集める。
 * 入れ子のクロージャ内部は別スコープなので除外する。
 *
 * @return array<int, string>
 */
$collectAssigned = function (array $nodes) use (&$collectAssigned): array {
    $names = [];

    foreach ($nodes as $node) {
        if ($node === null) {
            continue;
        }

        if ($node instanceof Node\Expr\Closure || $node instanceof Node\FunctionLike && ! $node instanceof Node\Expr\ArrowFunction) {
            continue;
        }

        if ($node instanceof Node\Expr\Assign || $node instanceof Node\Expr\AssignRef || $node instanceof Node\Expr\AssignOp) {
            $names = array_merge($names, targetNames($node->var));
        }

        if ($node instanceof Node\Stmt\Foreach_) {
            $names = array_merge($names, targetNames($node->valueVar));

            if ($node->keyVar !== null) {
                $names = array_merge($names, targetNames($node->keyVar));
            }
        }

        if ($node instanceof Node\Stmt\Catch_ && $node->var !== null) {
            $names = array_merge($names, targetNames($node->var));
        }

        if ($node instanceof Node\Stmt\Static_) {
            foreach ($node->vars as $staticVar) {
                $names = array_merge($names, targetNames($staticVar->var));
            }
        }

        if ($node instanceof Node\Expr\Isset_ || $node instanceof Node\Expr\Empty_) {
            // isset/empty は未定義でも安全なので、読み取りとして扱わない
        }

        $names = array_merge($names, $collectAssigned(childNodes($node)));
    }

    return $names;
};

/**
 * 代入先から変数名を取り出す（list() や配列展開にも対応）。
 *
 * @return array<int, string>
 */
function targetNames(?Node $node): array
{
    if ($node === null) {
        return [];
    }

    if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
        return [$node->name];
    }

    if ($node instanceof Node\Expr\List_ || $node instanceof Node\Expr\Array_) {
        $names = [];

        foreach ($node->items as $item) {
            if ($item !== null) {
                $names = array_merge($names, targetNames($item->value));
            }
        }

        return $names;
    }

    if ($node instanceof Node\Expr\ArrayDimFetch || $node instanceof Node\Expr\PropertyFetch) {
        return targetNames($node->var);
    }

    return [];
}

/**
 * そのスコープ自身で使われている変数を集める（入れ子の関数は別スコープなので除外）。
 *
 * @param  array<int, Node|null>  $nodes
 * @return array<int, Node\Expr\Variable>
 */
function ownScopeVariables(array $nodes): array
{
    $variables = [];

    foreach ($nodes as $node) {
        if ($node === null) {
            continue;
        }

        if ($node instanceof Node\FunctionLike) {
            // 入れ子の関数・クロージャ・アロー関数は別スコープ。
            // ただしアロー関数は外側を自動で取り込むため、本体だけは同じスコープとして見る。
            if ($node instanceof Node\Expr\ArrowFunction) {
                $variables = array_merge($variables, ownScopeVariables([$node->expr]));
            }

            continue;
        }

        if ($node instanceof Node\Expr\Variable) {
            $variables[] = $node;
        }

        $variables = array_merge($variables, ownScopeVariables(childNodes($node)));
    }

    return $variables;
}

/**
 * @return array<int, Node>
 */
function childNodes(Node $node): array
{
    $children = [];

    foreach ($node->getSubNodeNames() as $name) {
        $sub = $node->$name;

        if ($sub instanceof Node) {
            $children[] = $sub;
        } elseif (is_array($sub)) {
            foreach ($sub as $item) {
                if ($item instanceof Node) {
                    $children[] = $item;
                }
            }
        }
    }

    return $children;
}

$files = [];
foreach ($targets as $target) {
    if (is_file($target)) {
        $files[] = $target;

        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);

foreach ($files as $file) {
    $code = file_get_contents($file);

    try {
        $ast = $parser->parse($code);
    } catch (Throwable $e) {
        $problems[] = [$file, 0, 'パースできません: '.$e->getMessage()];

        continue;
    }

    /** @var array<int, Node\Expr\Closure> $closures */
    $closures = $finder->findInstanceOf($ast, Node\Expr\Closure::class);

    foreach ($closures as $closure) {
        $known = $always;

        foreach ($closure->uses as $use) {
            if (is_string($use->var->name)) {
                $known[] = $use->var->name;
            }
        }

        foreach ($closure->params as $param) {
            $known = array_merge($known, targetNames($param->var));
        }

        $known = array_merge($known, $collectAssigned($closure->stmts));

        // クロージャ本体（入れ子の関数を除く）で読んでいる変数
        $reads = ownScopeVariables($closure->stmts);

        $reported = [];

        foreach ($reads as $variable) {
            if (! is_string($variable->name) || in_array($variable->name, $known, true)) {
                continue;
            }

            if (in_array($variable->name, $reported, true)) {
                continue;
            }

            // 入れ子クロージャの引数などは別スコープなので、そこにあるものは除外する
            $inner = false;
            foreach ($finder->findInstanceOf($closure->stmts, Node\FunctionLike::class) as $nested) {
                if ($nested === $closure) {
                    continue;
                }

                foreach ($nested->getParams() as $param) {
                    if (in_array($variable->name, targetNames($param->var), true)) {
                        $inner = true;
                    }
                }

                if ($nested instanceof Node\Expr\ArrowFunction || $nested instanceof Node\Expr\Closure) {
                    foreach (($nested->uses ?? []) as $use) {
                        if (is_string($use->var->name) && $use->var->name === $variable->name) {
                            $inner = true;
                        }
                    }
                }
            }

            if ($inner) {
                continue;
            }

            $reported[] = $variable->name;
            $problems[] = [$file, $variable->getStartLine(), '$'.$variable->name.' がクロージャの use に含まれていません'];
        }
    }
}

$root = realpath(__DIR__.'/..').'/';

foreach ($problems as [$file, $line, $message]) {
    echo str_replace($root, '', $file).':'.$line.' '.$message.PHP_EOL;
}

if ($problems === []) {
    echo 'クロージャの use 漏れは見つかりませんでした（'.count($files).'ファイル）'.PHP_EOL;
    exit(0);
}

echo PHP_EOL.count($problems).'件の問題が見つかりました'.PHP_EOL;
exit(1);
