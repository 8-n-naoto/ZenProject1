<?php

namespace App\Enums;

/**
 * 完売リスク。当日の巡回順を決めるときの優先度。
 */
enum SelloutRisk: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $risk) => $risk->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::High => '完売しやすい',
            self::Medium => 'やや心配',
            self::Low => '余裕あり',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::High => '早めに',
            self::Medium => 'やや心配',
            self::Low => '余裕あり',
        };
    }

    /**
     * 小さいほど先に回る。
     */
    public function order(): int
    {
        return match ($this) {
            self::High => 0,
            self::Medium => 1,
            self::Low => 2,
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::High => 'bg-rose-100 text-rose-800',
            self::Medium => 'bg-amber-100 text-amber-800',
            self::Low => 'bg-slate-100 text-slate-600',
        };
    }
}
