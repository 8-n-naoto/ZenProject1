<?php

namespace App\Enums;

/**
 * イベントで頒布される商品の状態。
 */
enum ProductStatus: string
{
    case Selling = 'selling';
    case SoldOut = 'sold_out';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Selling => '頒布あり',
            self::SoldOut => '完売',
            self::Cancelled => '頒布中止',
        };
    }

    /**
     * 購入希望を受け付けられる状態か。
     */
    public function isPurchasable(): bool
    {
        return $this === self::Selling;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Selling => 'bg-emerald-100 text-emerald-800',
            self::SoldOut => 'bg-slate-200 text-slate-600',
            self::Cancelled => 'bg-rose-100 text-rose-800',
        };
    }
}
