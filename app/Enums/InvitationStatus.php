<?php

namespace App\Enums;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
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
            self::Pending => '返答待ち',
            self::Accepted => '参加',
            self::Declined => '辞退',
            self::Cancelled => '取消',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800',
            self::Accepted => 'bg-emerald-100 text-emerald-800',
            self::Declined => 'bg-slate-100 text-slate-600',
            self::Cancelled => 'bg-slate-100 text-slate-600',
        };
    }
}
