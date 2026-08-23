<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Applied = 'applied';
    case Withdrawn = 'withdrawn';

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
            self::Pending => '承認待ち',
            self::Approved => '可決',
            self::Rejected => '否決',
            self::Applied => '適用済み',
            self::Withdrawn => '取り下げ',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800',
            self::Approved => 'bg-sky-100 text-sky-800',
            self::Rejected => 'bg-rose-100 text-rose-800',
            self::Applied => 'bg-emerald-100 text-emerald-800',
            self::Withdrawn => 'bg-slate-100 text-slate-600',
        };
    }
}
