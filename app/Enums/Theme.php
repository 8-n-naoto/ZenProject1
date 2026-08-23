<?php

namespace App\Enums;

/**
 * 画面の見た目（配色・書体・角の丸み）の選択肢。
 *
 * 機能や画面構成は共通で、CSS の見た目だけが切り替わる。
 * 実体は public/css/theme.css の [data-theme="..."] ブロック。
 */
enum Theme: string
{
    /** ソフト＆ラウンド。丸ゴシックと温かみのある配色 */
    case Soft = 'soft';

    /** 会場モード。暗い背景で屋内の視認性を優先する */
    case Venue = 'venue';

    /** ミニマル・エディトリアル。罫線主体で情報密度を上げる */
    case Editorial = 'editorial';

    public static function default(): self
    {
        return self::Soft;
    }

    /**
     * ログイン中の利用者が選んでいる見た目。未ログイン時は既定値。
     */
    public static function current(): self
    {
        return auth()->check() ? auth()->user()->preferredTheme() : self::default();
    }

    /**
     * 保存値が壊れていても画面が落ちないようにする。
     */
    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

    public function label(): string
    {
        return match ($this) {
            self::Soft => 'ソフト',
            self::Venue => '会場モード',
            self::Editorial => 'ミニマル',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Soft => '丸みのある書体と温かい配色。普段づかい向けの既定の見た目です。',
            self::Venue => '暗い背景で明るい表示を抑えます。会場や夜間で目が疲れにくく、電池も長持ちします。',
            self::Editorial => '影や塗りを減らし、罫線と余白で整理します。一画面に多くの情報が入ります。',
        };
    }

    /**
     * ブラウザのアドレスバーなどに使われる色。画面の背景色に合わせる。
     */
    public function themeColor(): string
    {
        return match ($this) {
            self::Soft => '#f6f1e9',
            self::Venue => '#0b0d10',
            self::Editorial => '#fbfaf8',
        };
    }

    /**
     * Google Fonts の読み込み先。通信できないときは端末の書体で表示される。
     */
    public function fontHref(): string
    {
        return match ($this) {
            self::Soft => 'https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@400;500;700;800&display=swap',
            self::Venue => 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+JP:wght@400;500;600;700&display=swap',
            self::Editorial => 'https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700;900&display=swap',
        };
    }

    /**
     * 設定画面の見本に使う色（背景・カード・アクセント）。
     *
     * @return array{bg: string, surface: string, accent: string, ink: string}
     */
    public function swatch(): array
    {
        return match ($this) {
            self::Soft => ['bg' => '#f6f1e9', 'surface' => '#ffffff', 'accent' => '#d97531', 'ink' => '#40382e'],
            self::Venue => ['bg' => '#0b0d10', 'surface' => '#15181d', 'accent' => '#c9ef5a', 'ink' => '#e8ecf1'],
            self::Editorial => ['bg' => '#fbfaf8', 'surface' => '#ffffff', 'accent' => '#4338ca', 'ink' => '#17181a'],
        };
    }

    /**
     * @return array<int, self>
     */
    public static function options(): array
    {
        return self::cases();
    }
}
