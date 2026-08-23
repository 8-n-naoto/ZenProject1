<?php

namespace App\Support;

/**
 * サークルの配置（例: 「東1 ア-12a」「西2ホール サ-05b」）を、
 * 当日回る順序に近い形で並べるためのソートキーを作る。
 */
class BoothSorter
{
    /** ホールの並び順 */
    private const HALL_ORDER = ['東' => 1, '西' => 2, '南' => 3, '北' => 4];

    /**
     * 比較可能な文字列キーを返す。配置未設定は最後に回る。
     */
    public static function key(?string $booth): string
    {
        $booth = trim((string) $booth);

        if ($booth === '') {
            return '9|9|9|zzzzzzzz|99999|z';
        }

        if (function_exists('mb_convert_kana')) {
            $booth = mb_convert_kana($booth, 'asKV', 'UTF-8');
        }

        $hallOrder = 8;
        $hallNumber = 0;

        if (preg_match('/([東西南北])\s*(\d+)?/u', $booth, $m) === 1) {
            $hallOrder = self::HALL_ORDER[$m[1]] ?? 8;
            $hallNumber = isset($m[2]) ? (int) $m[2] : 0;
        }

        // 「東1ホール ア-12a」のように「ホール」を含む表記でも誤認しないよう、先に取り除く
        $booth = preg_replace('/(ホール|ﾎｰﾙ|hall)/ui', ' ', $booth) ?? $booth;

        // ブロック（カタカナ・ひらがな・英字の連続）
        $block = '';
        if (preg_match('/([ぁ-んァ-ヶA-Za-z]{1,4})\s*[-ー－‐−–]/u', $booth, $m) === 1) {
            $block = $m[1];
        }

        // スペース番号（ブロックの後ろの数字）
        $number = 0;
        $sub = '';
        if (preg_match('/[-ー－‐−–]\s*(\d+)\s*([abABａｂ])?/u', $booth, $m) === 1) {
            $number = (int) $m[1];
            $sub = mb_strtolower($m[2] ?? '', 'UTF-8');
        }

        return sprintf(
            '%d|%02d|%s|%05d|%s',
            $hallOrder,
            $hallNumber,
            str_pad(self::blockKey($block), 8, 'z'),
            $number,
            $sub === '' ? 'z' : $sub
        );
    }

    /**
     * ブロック名を五十音順に近い順序で比較できる形にする。
     */
    private static function blockKey(string $block): string
    {
        if ($block === '') {
            return 'zzzz';
        }

        // ひらがなはカタカナに寄せて、コードポイント順で五十音順になるようにする
        if (function_exists('mb_convert_kana')) {
            $block = mb_convert_kana($block, 'C', 'UTF-8');
        }

        return mb_strtolower($block, 'UTF-8');
    }

    /**
     * 配置文字列の配列をソートする（テスト・表示用）。
     *
     * @param  array<int, string|null>  $booths
     * @return array<int, string|null>
     */
    public static function sort(array $booths): array
    {
        usort($booths, fn ($a, $b) => strcmp(self::key($a), self::key($b)));

        return $booths;
    }
}
