<?php

namespace App\Enums;

/**
 * 購入結果の状態。予定数量と実際に購入できた数量の関係を表す。
 */
enum PurchaseResultStatus: string
{
    case Completed = 'completed';
    case Shortage = 'shortage';
    case Excess = 'excess';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    public static function fromQuantities(int $planned, int $purchased): self
    {
        return match (true) {
            $purchased < $planned => self::Shortage,
            $purchased > $planned => self::Excess,
            default => self::Completed,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Completed => '予定どおり',
            self::Shortage => '不足',
            self::Excess => '超過',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Completed => 'bg-emerald-100 text-emerald-800',
            self::Shortage => 'bg-rose-100 text-rose-800',
            self::Excess => 'bg-amber-100 text-amber-800',
        };
    }
}
