<?php

/**
 * Tailwind 互換ユーティリティCSSのビルドスクリプト。
 *
 * このプロジェクトは Tailwind のクラス名で Blade を記述しているが、
 * npm レジストリを利用できない環境でも動作するよう、使用しているクラスだけを
 * 抽出した CSS を public/css/app.css として生成する。
 *
 *   php tools/build-css.php
 *
 * Node が使える環境では `npm install && npm run build` で本家 Tailwind に
 * 差し替えられる（レイアウトは build/manifest.json があればそちらを優先する）。
 */
$root = dirname(__DIR__);

$scanDirs = [$root.'/resources/views', $root.'/app'];
$outFile = $root.'/public/css/app.css';

/* ------------------------------------------------------------------ */
/* 値マップ */
/* ------------------------------------------------------------------ */

$space = [
    '0' => '0px', 'px' => '1px', '0.5' => '0.125rem', '1' => '0.25rem', '1.5' => '0.375rem',
    '2' => '0.5rem', '2.5' => '0.625rem', '3' => '0.75rem', '3.5' => '0.875rem', '4' => '1rem',
    '5' => '1.25rem', '6' => '1.5rem', '7' => '1.75rem', '8' => '2rem', '9' => '2.25rem',
    '10' => '2.5rem', '11' => '2.75rem', '12' => '3rem', '14' => '3.5rem', '16' => '4rem',
    '20' => '5rem', '24' => '6rem', '28' => '7rem', '32' => '8rem', '40' => '10rem',
    '48' => '12rem', '56' => '14rem', '64' => '16rem', '72' => '18rem', '80' => '20rem', '96' => '24rem',
];

$palette = [
    'slate' => ['50' => '#f8fafc', '100' => '#f1f5f9', '200' => '#e2e8f0', '300' => '#cbd5e1', '400' => '#94a3b8', '500' => '#64748b', '600' => '#475569', '700' => '#334155', '800' => '#1e293b', '900' => '#0f172a', '950' => '#020617'],
    'sky' => ['50' => '#f0f9ff', '100' => '#e0f2fe', '200' => '#bae6fd', '300' => '#7dd3fc', '400' => '#38bdf8', '500' => '#0ea5e9', '600' => '#0284c7', '700' => '#0369a1', '800' => '#075985', '900' => '#0c4a6e'],
    'emerald' => ['50' => '#ecfdf5', '100' => '#d1fae5', '200' => '#a7f3d0', '300' => '#6ee7b7', '400' => '#34d399', '500' => '#10b981', '600' => '#059669', '700' => '#047857', '800' => '#065f46', '900' => '#064e3b'],
    'amber' => ['50' => '#fffbeb', '100' => '#fef3c7', '200' => '#fde68a', '300' => '#fcd34d', '400' => '#fbbf24', '500' => '#f59e0b', '600' => '#d97706', '700' => '#b45309', '800' => '#92400e', '900' => '#78350f'],
    'rose' => ['50' => '#fff1f2', '100' => '#ffe4e6', '200' => '#fecdd3', '300' => '#fda4af', '400' => '#fb7185', '500' => '#f43f5e', '600' => '#e11d48', '700' => '#be123c', '800' => '#9f1239', '900' => '#881337'],
    'indigo' => ['50' => '#eef2ff', '100' => '#e0e7ff', '200' => '#c7d2fe', '300' => '#a5b4fc', '400' => '#818cf8', '500' => '#6366f1', '600' => '#4f46e5', '700' => '#4338ca', '800' => '#3730a3', '900' => '#312e81'],
    'violet' => ['50' => '#f5f3ff', '100' => '#ede9fe', '200' => '#ddd6fe', '300' => '#c4b5fd', '400' => '#a78bfa', '500' => '#8b5cf6', '600' => '#7c3aed', '700' => '#6d28d9', '800' => '#5b21b6', '900' => '#4c1d95'],
];

$colors = ['white' => '#ffffff', 'black' => '#000000', 'transparent' => 'transparent', 'current' => 'currentColor'];
foreach ($palette as $name => $shades) {
    foreach ($shades as $shade => $hex) {
        $colors[$name.'-'.$shade] = $hex;
    }
}

$fontSize = [
    'xs' => ['0.75rem', '1rem'], 'sm' => ['0.875rem', '1.25rem'], 'base' => ['1rem', '1.5rem'],
    'lg' => ['1.125rem', '1.75rem'], 'xl' => ['1.25rem', '1.75rem'], '2xl' => ['1.5rem', '2rem'],
    '3xl' => ['1.875rem', '2.25rem'], '4xl' => ['2.25rem', '2.5rem'], '5xl' => ['3rem', '1'],
];

$radius = ['none' => '0px', 'sm' => '0.125rem', '' => '0.25rem', 'md' => '0.375rem', 'lg' => '0.5rem', 'xl' => '0.75rem', '2xl' => '1rem', '3xl' => '1.5rem', 'full' => '9999px'];

$shadow = [
    'sm' => '0 1px 2px 0 rgb(0 0 0 / 0.05)',
    '' => '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)',
    'md' => '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
    'lg' => '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
    'xl' => '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
    'none' => '0 0 #0000',
];

$maxWidth = ['xs' => '20rem', 'sm' => '24rem', 'md' => '28rem', 'lg' => '32rem', 'xl' => '36rem', '2xl' => '42rem', '3xl' => '48rem', '4xl' => '56rem', '5xl' => '64rem', '6xl' => '72rem', '7xl' => '80rem', 'full' => '100%', 'none' => 'none', 'prose' => '65ch'];

$fractions = ['1/2' => '50%', '1/3' => '33.333333%', '2/3' => '66.666667%', '1/4' => '25%', '3/4' => '75%', 'full' => '100%', 'auto' => 'auto', 'fit' => 'fit-content', 'min' => 'min-content', 'max' => 'max-content', 'screen' => '100vw'];

/* ------------------------------------------------------------------ */
/* ユーティリティ解決 */
/* ------------------------------------------------------------------ */

/**
 * クラス名からCSS宣言を返す。解決できなければ null。
 */
$resolve = function (string $c) use ($space, $colors, $fontSize, $radius, $shadow, $maxWidth, $fractions): ?string {
    static $statics = null;

    if ($statics === null) {
        $statics = [
            'block' => 'display:block', 'inline-block' => 'display:inline-block', 'inline' => 'display:inline',
            'flex' => 'display:flex', 'inline-flex' => 'display:inline-flex', 'grid' => 'display:grid',
            'hidden' => 'display:none', 'table' => 'display:table', 'contents' => 'display:contents',
            'flex-row' => 'flex-direction:row', 'flex-col' => 'flex-direction:column',
            'flex-wrap' => 'flex-wrap:wrap', 'flex-nowrap' => 'flex-wrap:nowrap',
            'flex-1' => 'flex:1 1 0%', 'flex-auto' => 'flex:1 1 auto', 'flex-none' => 'flex:none',
            'grow' => 'flex-grow:1', 'grow-0' => 'flex-grow:0', 'shrink' => 'flex-shrink:1', 'shrink-0' => 'flex-shrink:0',
            'items-start' => 'align-items:flex-start', 'items-center' => 'align-items:center', 'items-end' => 'align-items:flex-end',
            'items-baseline' => 'align-items:baseline', 'items-stretch' => 'align-items:stretch',
            'justify-start' => 'justify-content:flex-start', 'justify-center' => 'justify-content:center',
            'justify-end' => 'justify-content:flex-end', 'justify-between' => 'justify-content:space-between',
            'justify-around' => 'justify-content:space-around', 'justify-evenly' => 'justify-content:space-evenly',
            'self-start' => 'align-self:flex-start', 'self-center' => 'align-self:center', 'self-end' => 'align-self:flex-end',
            'text-left' => 'text-align:left', 'text-center' => 'text-align:center', 'text-right' => 'text-align:right',
            'font-normal' => 'font-weight:400', 'font-medium' => 'font-weight:500', 'font-semibold' => 'font-weight:600', 'font-bold' => 'font-weight:700',
            'italic' => 'font-style:italic', 'not-italic' => 'font-style:normal',
            'underline' => 'text-decoration-line:underline', 'no-underline' => 'text-decoration-line:none',
            'line-through' => 'text-decoration-line:line-through',
            'uppercase' => 'text-transform:uppercase', 'lowercase' => 'text-transform:lowercase', 'capitalize' => 'text-transform:capitalize',
            'truncate' => 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap',
            'whitespace-nowrap' => 'white-space:nowrap', 'whitespace-pre-line' => 'white-space:pre-line', 'whitespace-pre-wrap' => 'white-space:pre-wrap',
            'break-words' => 'overflow-wrap:break-word', 'break-all' => 'word-break:break-all',
            'relative' => 'position:relative', 'absolute' => 'position:absolute', 'fixed' => 'position:fixed',
            'sticky' => 'position:sticky', 'static' => 'position:static',
            'inset-0' => 'inset:0px', 'top-0' => 'top:0px', 'right-0' => 'right:0px', 'bottom-0' => 'bottom:0px', 'left-0' => 'left:0px',
            'overflow-hidden' => 'overflow:hidden', 'overflow-auto' => 'overflow:auto',
            'overflow-x-auto' => 'overflow-x:auto', 'overflow-y-auto' => 'overflow-y:auto',
            'cursor-pointer' => 'cursor:pointer', 'cursor-not-allowed' => 'cursor:not-allowed', 'cursor-default' => 'cursor:default',
            'select-none' => 'user-select:none',
            'appearance-none' => 'appearance:none;-webkit-appearance:none',
            'object-cover' => 'object-fit:cover', 'object-contain' => 'object-fit:contain',
            'list-none' => 'list-style-type:none', 'list-disc' => 'list-style-type:disc',
            'border-collapse' => 'border-collapse:collapse',
            'align-middle' => 'vertical-align:middle', 'align-top' => 'vertical-align:top',
            'mx-auto' => 'margin-left:auto;margin-right:auto', 'ml-auto' => 'margin-left:auto', 'mr-auto' => 'margin-right:auto',
            'transition' => 'transition-property:color,background-color,border-color,text-decoration-color,fill,stroke,opacity,box-shadow,transform,filter,backdrop-filter;transition-timing-function:cubic-bezier(0.4,0,0.2,1);transition-duration:150ms',
            'transition-colors' => 'transition-property:color,background-color,border-color,text-decoration-color,fill,stroke;transition-timing-function:cubic-bezier(0.4,0,0.2,1);transition-duration:150ms',
            'transition-transform' => 'transition-property:transform;transition-timing-function:cubic-bezier(0.4,0,0.2,1);transition-duration:150ms',
            'duration-100' => 'transition-duration:100ms', 'duration-150' => 'transition-duration:150ms', 'duration-200' => 'transition-duration:200ms', 'duration-300' => 'transition-duration:300ms',
            'rotate-180' => 'transform:rotate(180deg)',
            'resize-none' => 'resize:none', 'resize-y' => 'resize:vertical',
            'sr-only' => 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border-width:0',
            'pointer-events-none' => 'pointer-events:none',
            'border-solid' => 'border-style:solid', 'border-dashed' => 'border-style:dashed', 'border-dotted' => 'border-style:dotted',
            'pb-safe' => 'padding-bottom:env(safe-area-inset-bottom, 0px)',
            'not-sr-only' => 'position:static;width:auto;height:auto;padding:0;margin:0;overflow:visible;clip:auto;white-space:normal',
            'min-h-screen' => 'min-height:100vh', 'min-h-dvh' => 'min-height:100dvh', 'min-h-full' => 'min-height:100%',
            'h-screen' => 'height:100vh', 'w-screen' => 'width:100vw',
            'min-w-0' => 'min-width:0px', 'max-h-screen' => 'max-height:100vh',
            'tabular-nums' => 'font-variant-numeric:tabular-nums',
            'antialiased' => '-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale',
        ];
    }

    if (isset($statics[$c])) {
        return $statics[$c];
    }

    // spacing 系
    $spacingProps = [
        'p' => ['padding'], 'px' => ['padding-left', 'padding-right'], 'py' => ['padding-top', 'padding-bottom'],
        'pt' => ['padding-top'], 'pr' => ['padding-right'], 'pb' => ['padding-bottom'], 'pl' => ['padding-left'],
        'm' => ['margin'], 'mx' => ['margin-left', 'margin-right'], 'my' => ['margin-top', 'margin-bottom'],
        'mt' => ['margin-top'], 'mr' => ['margin-right'], 'mb' => ['margin-bottom'], 'ml' => ['margin-left'],
        'gap' => ['gap'], 'gap-x' => ['column-gap'], 'gap-y' => ['row-gap'],
        'top' => ['top'], 'right' => ['right'], 'bottom' => ['bottom'], 'left' => ['left'],
    ];
    // 先頭のマイナス（例: -mx-3, -top-1）は負の値として扱う
    $negative = str_starts_with($c, '-');
    $base = $negative ? substr($c, 1) : $c;

    foreach ($spacingProps as $prefix => $props) {
        if (str_starts_with($base, $prefix.'-')) {
            $key = substr($base, strlen($prefix) + 1);

            if (isset($space[$key])) {
                $value = $space[$key];

                if ($negative && $value !== '0px') {
                    $value = '-'.$value;
                }

                return implode(';', array_map(static fn ($p) => $p.':'.$value, $props));
            }
        }
    }

    if ($negative) {
        // 負の値に対応しているのは余白・位置のみ
        return null;
    }

    // space-y-* / space-x-*（子要素の間隔）
    if (preg_match('/^space-(x|y)-(.+)$/', $c, $m) && isset($space[$m[2]])) {
        $v = $space[$m[2]];

        return $m[1] === 'y'
            ? '__CHILD__margin-top:'.$v
            : '__CHILDX__margin-left:'.$v;
    }

    // 色
    if (preg_match('/^(text|bg|border|ring|placeholder|decoration|fill|from|divide)-(.+)$/', $c, $m)) {
        $type = $m[1];
        $name = $m[2];
        if (isset($colors[$name])) {
            $hex = $colors[$name];

            return match ($type) {
                'text' => 'color:'.$hex,
                'bg' => 'background-color:'.$hex,
                'border' => 'border-color:'.$hex,
                'divide' => '__CHILD__border-top-color:'.$hex,
                'ring' => '--zp-ring-color:'.$hex,
                'placeholder' => '__PLACEHOLDER__color:'.$hex,
                'decoration' => 'text-decoration-color:'.$hex,
                'fill' => 'fill:'.$hex,
                default => null,
            };
        }
    }

    // フォントサイズ
    if (preg_match('/^text-(.+)$/', $c, $m) && isset($fontSize[$m[1]])) {
        [$size, $lh] = $fontSize[$m[1]];

        return 'font-size:'.$size.';line-height:'.$lh;
    }

    // 角丸
    if (preg_match('/^rounded(-(t|r|b|l|tl|tr|br|bl))?(-(.+))?$/', $c, $m)) {
        $side = $m[2] ?? '';
        $key = $m[4] ?? '';
        if (isset($radius[$key])) {
            $v = $radius[$key];
            $map = [
                '' => ['border-radius'],
                't' => ['border-top-left-radius', 'border-top-right-radius'],
                'b' => ['border-bottom-left-radius', 'border-bottom-right-radius'],
                'l' => ['border-top-left-radius', 'border-bottom-left-radius'],
                'r' => ['border-top-right-radius', 'border-bottom-right-radius'],
                'tl' => ['border-top-left-radius'], 'tr' => ['border-top-right-radius'],
                'br' => ['border-bottom-right-radius'], 'bl' => ['border-bottom-left-radius'],
            ];
            if (isset($map[$side])) {
                return implode(';', array_map(static fn ($p) => $p.':'.$v, $map[$side]));
            }
        }
    }

    // 影
    if (preg_match('/^shadow(-(.+))?$/', $c, $m)) {
        $key = $m[2] ?? '';
        if (isset($shadow[$key])) {
            return 'box-shadow:'.$shadow[$key];
        }
    }

    // 枠線の太さ
    if (preg_match('/^border(-(t|r|b|l|x|y))?(-(0|2|4|8))?$/', $c, $m)) {
        $side = $m[2] ?? '';
        $w = isset($m[4]) && $m[4] !== '' ? $m[4].'px' : '1px';
        $map = [
            '' => ['border-width'],
            't' => ['border-top-width'], 'r' => ['border-right-width'],
            'b' => ['border-bottom-width'], 'l' => ['border-left-width'],
            'x' => ['border-left-width', 'border-right-width'],
            'y' => ['border-top-width', 'border-bottom-width'],
        ];

        return implode(';', array_map(static fn ($p) => $p.':'.$w, $map[$side]));
    }

    if (preg_match('/^divide-y$/', $c)) {
        return '__CHILD__border-top-width:1px;border-top-style:solid';
    }

    // ring
    if (preg_match('/^ring(-(0|1|2|4))?$/', $c, $m)) {
        $w = isset($m[2]) && $m[2] !== '' ? $m[2] : '3';

        return 'box-shadow:0 0 0 '.$w.'px var(--zp-ring-color, #0ea5e9)';
    }

    // 幅・高さ
    if (preg_match('/^w-(.+)$/', $c, $m)) {
        $k = $m[1];
        if (isset($space[$k])) {
            return 'width:'.$space[$k];
        }
        if (isset($fractions[$k])) {
            return 'width:'.$fractions[$k];
        }
    }
    if (preg_match('/^h-(.+)$/', $c, $m)) {
        $k = $m[1];
        if (isset($space[$k])) {
            return 'height:'.$space[$k];
        }
        if (isset($fractions[$k])) {
            return 'height:'.($k === 'screen' ? '100vh' : $fractions[$k]);
        }
    }
    if (preg_match('/^min-h-(.+)$/', $c, $m) && isset($space[$m[1]])) {
        return 'min-height:'.$space[$m[1]];
    }
    if (preg_match('/^max-w-(.+)$/', $c, $m) && isset($maxWidth[$m[1]])) {
        return 'max-width:'.$maxWidth[$m[1]];
    }
    if (preg_match('/^min-w-(.+)$/', $c, $m) && isset($maxWidth[$m[1]])) {
        return 'min-width:'.$maxWidth[$m[1]];
    }

    // グリッド
    if (preg_match('/^grid-cols-(\d+)$/', $c, $m)) {
        return 'grid-template-columns:repeat('.$m[1].',minmax(0,1fr))';
    }
    if (preg_match('/^col-span-(\d+)$/', $c, $m)) {
        return 'grid-column:span '.$m[1].' / span '.$m[1];
    }
    if ($c === 'col-span-full') {
        return 'grid-column:1 / -1';
    }

    // 行間・字間
    $leading = ['none' => '1', 'tight' => '1.25', 'snug' => '1.375', 'normal' => '1.5', 'relaxed' => '1.625', 'loose' => '2'];
    if (preg_match('/^leading-(.+)$/', $c, $m)) {
        if (isset($leading[$m[1]])) {
            return 'line-height:'.$leading[$m[1]];
        }
        if (isset($space[$m[1]])) {
            return 'line-height:'.$space[$m[1]];
        }
    }
    $tracking = ['tight' => '-0.025em', 'normal' => '0em', 'wide' => '0.025em', 'wider' => '0.05em', 'widest' => '0.1em'];
    if (preg_match('/^tracking-(.+)$/', $c, $m) && isset($tracking[$m[1]])) {
        return 'letter-spacing:'.$tracking[$m[1]];
    }

    // 不透明度・z-index
    if (preg_match('/^opacity-(\d+)$/', $c, $m)) {
        return 'opacity:'.($m[1] / 100);
    }
    if (preg_match('/^z-(\d+)$/', $c, $m)) {
        return 'z-index:'.$m[1];
    }

    return null;
};

/* ------------------------------------------------------------------ */
/* クラス抽出 */
/* ------------------------------------------------------------------ */

$candidates = [];
$rii = function (string $dir) use (&$rii, &$candidates) {
    if (! is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir.'/'.$entry;
        if (is_dir($path)) {
            $rii($path);

            continue;
        }
        if (! preg_match('/\.(blade\.php|php)$/', $entry)) {
            continue;
        }
        $content = file_get_contents($path);
        if (preg_match_all('/[A-Za-z0-9:_\/\.\-]+/', $content, $m)) {
            foreach ($m[0] as $token) {
                $candidates[$token] = true;
            }
        }
    }
};
foreach ($scanDirs as $dir) {
    $rii($dir);
}

$variants = [
    'hover' => ':hover', 'focus' => ':focus', 'active' => ':active',
    'disabled' => ':disabled', 'focus-within' => ':focus-within',
    'first' => ':first-child', 'last' => ':last-child',
];
$screens = ['sm' => '640px', 'md' => '768px', 'lg' => '1024px', 'xl' => '1280px'];

$rules = [];          // media => [selector => decls]
foreach (array_keys($candidates) as $class) {
    $parts = explode(':', $class);
    $base = array_pop($parts);
    $media = null;
    $pseudo = '';
    foreach ($parts as $p) {
        if (isset($screens[$p])) {
            $media = $screens[$p];
        } elseif (isset($variants[$p])) {
            $pseudo .= $variants[$p];
        } else {
            continue 2; // 未知のバリアントは無視
        }
    }

    $decl = $resolve($base);
    if ($decl === null) {
        continue;
    }

    $escaped = preg_replace('/([:\/\.])/', '\\\\$1', $class);
    $selector = '.'.$escaped.$pseudo;
    $childSuffix = '';

    if (str_starts_with($decl, '__CHILD__')) {
        $decl = substr($decl, 9);
        $selector = '.'.$escaped.' > * + *';
    } elseif (str_starts_with($decl, '__CHILDX__')) {
        $decl = substr($decl, 10);
        $selector = '.'.$escaped.' > * + *';
    } elseif (str_starts_with($decl, '__PLACEHOLDER__')) {
        $decl = substr($decl, 15);
        $selector = '.'.$escaped.'::placeholder';
    }

    $key = $media ?? '';
    $rules[$key][$selector] = $decl.$childSuffix;
}

/* ------------------------------------------------------------------ */
/* 出力 */
/* ------------------------------------------------------------------ */

$base = <<<'CSS'
/*! ZenProject1 utility stylesheet - generated by tools/build-css.php. 直接編集しないこと。 */
*, ::before, ::after { box-sizing: border-box; border-width: 0; border-style: solid; border-color: #e2e8f0; }
* { margin: 0; padding: 0; }
html { -webkit-text-size-adjust: 100%; line-height: 1.5; font-family: "Hiragino Sans", "Hiragino Kaku Gothic ProN", "Noto Sans JP", "Yu Gothic UI", Meiryo, ui-sans-serif, system-ui, sans-serif; }
body { min-height: 100dvh; line-height: inherit; background-color: #f1f5f9; color: #0f172a; -webkit-font-smoothing: antialiased; }
h1, h2, h3, h4, h5, h6 { font-size: inherit; font-weight: inherit; }
a { color: inherit; text-decoration: inherit; }
button, input, optgroup, select, textarea { font-family: inherit; font-size: 100%; font-weight: inherit; line-height: inherit; color: inherit; margin: 0; padding: 0; }
button, select { text-transform: none; }
button, [type='button'], [type='submit'] { -webkit-appearance: button; background-color: transparent; background-image: none; cursor: pointer; }
:disabled { cursor: default; }
img, svg, video, canvas { display: block; max-width: 100%; height: auto; }
ul, ol { list-style: none; }
table { border-collapse: collapse; }
summary { cursor: pointer; list-style: none; }
summary::-webkit-details-marker { display: none; }
:focus-visible { outline: 2px solid #0ea5e9; outline-offset: 2px; }
input, textarea, select { background-color: #ffffff; }
CSS;

$out = $base."\n";

$emit = function (array $selectors): string {
    $css = '';
    foreach ($selectors as $sel => $decl) {
        $css .= $sel.'{'.$decl.'}'."\n";
    }

    return $css;
};

if (isset($rules[''])) {
    $out .= $emit($rules['']);
}
foreach ($screens as $name => $width) {
    if (isset($rules[$width])) {
        $out .= '@media (min-width:'.$width.'){'."\n".$emit($rules[$width]).'}'."\n";
    }
}

if (! is_dir(dirname($outFile))) {
    mkdir(dirname($outFile), 0755, true);
}
file_put_contents($outFile, $out);

$count = 0;
foreach ($rules as $set) {
    $count += count($set);
}
echo 'generated '.$count.' rules -> '.str_replace($root.'/', '', $outFile).' ('.number_format(strlen($out)).' bytes)'.PHP_EOL;

/*
|--------------------------------------------------------------------------
| テーマ層を public へ複写する
|--------------------------------------------------------------------------
| resources/css/theme.css が正。手書きのCSSなので生成はしないが、
| public/ を「ビルド成果物だけが置かれる場所」に保つため、ここで写す。
| Service Worker のキャッシュ名の計算より前に行う必要がある。
*/
$themeSrc = $root.'/resources/css/theme.css';
$themeDest = $root.'/public/css/theme.css';

if (is_file($themeSrc)) {
    $current = is_file($themeDest) ? file_get_contents($themeDest) : null;
    $next = file_get_contents($themeSrc);

    if ($current !== $next) {
        file_put_contents($themeDest, $next);
        echo 'copied theme -> public/css/theme.css ('.number_format(strlen($next)).' bytes)'.PHP_EOL;
    } else {
        echo 'theme stylesheet is up to date'.PHP_EOL;
    }
}

/*
|--------------------------------------------------------------------------
| Service Worker のキャッシュ名を更新する
|--------------------------------------------------------------------------
| sw.js はプリキャッシュした静的ファイルを「キャッシュ優先」で返す。
| キャッシュ名が同じままだと、CSSやオフライン案内を更新しても
| 一度アクセスした利用者には古いものが返り続ける。
|
| デプロイのたびに手で名前を上げるのは忘れるので、
| プリキャッシュ対象の中身から求めたハッシュを埋め込む。
*/
$swFile = $root.'/public/sw.js';

if (is_file($swFile)) {
    $sw = file_get_contents($swFile);

    // sw.js の ASSETS に並んでいるパスをそのまま対象にする
    $assets = [];

    if (preg_match('/var ASSETS = \[(.*?)\];/s', $sw, $m) === 1) {
        preg_match_all("/'([^']+)'/", $m[1], $paths);
        $assets = $paths[1];
    }

    $material = '';

    foreach ($assets as $assetPath) {
        $absolute = $root.'/public/'.ltrim($assetPath, '/');
        $material .= $assetPath.':'.(is_file($absolute) ? sha1_file($absolute) : 'missing').'|';
    }

    $version = substr(sha1($material), 0, 12);
    $updated = preg_replace("/var CACHE = 'kyodo-static-[^']*';/", "var CACHE = 'kyodo-static-".$version."';", $sw, 1);

    if ($updated !== null && $updated !== $sw) {
        file_put_contents($swFile, $updated);
        echo 'updated service worker cache -> kyodo-static-'.$version.PHP_EOL;
    } else {
        echo 'service worker cache is up to date (kyodo-static-'.$version.')'.PHP_EOL;
    }
}
