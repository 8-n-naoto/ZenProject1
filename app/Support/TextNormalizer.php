<?php

namespace App\Support;

/**
 * 名称の表記ゆれを吸収するための正規化。
 *
 * 同一サークルの重複登録を検知するために使用する。
 */
class TextNormalizer
{
    /**
     * 全角英数・記号を半角に、カタカナを全角に揃え、空白と記号を除いて小文字化する。
     */
    public static function key(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (function_exists('mb_convert_kana')) {
            // r: 全角英数→半角, n: 全角数字→半角, a: 全角英数記号→半角, s: 全角スペース→半角, K: 半角カナ→全角
            $value = mb_convert_kana($value, 'asKV', 'UTF-8');
        }

        $value = mb_strtolower($value, 'UTF-8');

        // 空白・区切り記号を除去する
        $value = preg_replace('/[\s\x{3000}]+/u', '', $value) ?? $value;
        $value = preg_replace('/[・･‐\-−–—~〜\/／\\\\]+/u', '', $value) ?? $value;

        return $value;
    }

    /**
     * 2つの名称が同一とみなせるか。
     */
    public static function matches(?string $a, ?string $b): bool
    {
        $keyA = self::key($a);

        return $keyA !== '' && $keyA === self::key($b);
    }
}
