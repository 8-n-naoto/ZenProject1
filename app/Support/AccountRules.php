<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * アカウント（ログインID・表示名・メールアドレス・パスワード）の入力ルール。
 *
 * 会員登録画面とテストユーザー作成コマンドの両方から使う。
 * 片方だけルールが変わって食い違うことを防ぐため、必ずここを通す。
 *
 * ユニーク判定について: `unique` ルールは生のクエリで判定するため、
 * 退会済み（ソフトデリート）のユーザーも対象に含まれる。
 * users テーブルのユニーク制約と一致する挙動なので、これが正しい。
 */
class AccountRules
{
    /** ログインIDの長さ（users.user_id は varchar(15)） */
    public const USER_ID_MIN = 5;

    public const USER_ID_MAX = 15;

    /**
     * @return array<int, mixed>
     */
    public static function userId(?int $ignoreUserId = null): array
    {
        return [
            'required',
            'string',
            'min:'.self::USER_ID_MIN,
            'max:'.self::USER_ID_MAX,
            'regex:/^[a-z0-9]+$/',
            Rule::unique('users', 'user_id')->ignore($ignoreUserId),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function name(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return array<int, mixed>
     */
    public static function email(?int $ignoreUserId = null): array
    {
        return [
            'required',
            'email',
            'max:255',
            Rule::unique('users', 'email')->ignore($ignoreUserId),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function password(bool $confirmed = false): array
    {
        return $confirmed
            ? ['required', 'confirmed', Password::defaults()]
            : ['required', Password::defaults()];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'user_id.regex' => 'ログインIDは英小文字と数字のみで入力してください。',
        ];
    }
}
