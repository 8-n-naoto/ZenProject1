<?php

namespace App\Enums;

/**
 * 精算（送金1件）の状態。
 */
enum SettlementStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';

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
            self::Pending => '未精算',
            self::Completed => '精算済み',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800',
            self::Completed => 'bg-emerald-100 text-emerald-800',
        };
    }
}
