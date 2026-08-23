<?php

namespace App\Enums;

/**
 * 支払いの状態。支払う側が報告し、受け取る側が確認して完了する。
 */
enum PaymentStatus: string
{
    case Reported = 'reported';
    case Confirmed = 'confirmed';
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
            self::Reported => '受取確認待ち',
            self::Confirmed => '受取確認済み',
            self::Cancelled => '取消',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Reported => 'bg-amber-100 text-amber-800',
            self::Confirmed => 'bg-emerald-100 text-emerald-800',
            self::Cancelled => 'bg-slate-100 text-slate-500',
        };
    }
}
