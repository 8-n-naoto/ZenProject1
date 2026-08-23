<?php

namespace App\Support;

/**
 * 検索キーワードの正規化。
 *
 * LIKE のワイルドカード（% _ \）をそのままDBに渡すと、
 * 「_」1文字で全件が引ける状態になってしまうため、必ずここを通す。
 */
class SearchKeyword
{
    /** 極端に長いキーワードでDBエラーになるのを防ぐ */
    public const MAX_LENGTH = 100;

    /**
     * 前後の空白を落とし、長さを制限する。
     */
    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return mb_substr($value, 0, self::MAX_LENGTH);
    }

    /**
     * LIKE のワイルドカードをエスケープする。
     * 併せて使う LIKE 句には `escape '\'`（既定）が必要。
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * 部分一致用のパターン。
     */
    public static function contains(string $value): string
    {
        return '%'.self::escapeLike($value).'%';
    }

    /**
     * 前方一致用のパターン。
     */
    public static function startsWith(string $value): string
    {
        return self::escapeLike($value).'%';
    }
}
