<?php

namespace App\Enums;

/**
 * グループ内の役割。
 *
 * DBには日本語文字列で保存されているため、値は日本語のままとする。
 */
enum GroupRole: string
{
    case HighestResponsible = '最高責任者';
    case Responsible = '責任者';
    case Member = '一般メンバー';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role) => $role->value, self::cases());
    }

    public function label(): string
    {
        return $this->value;
    }

    /**
     * 権限の強さ。数値が大きいほど強い。
     */
    public function rank(): int
    {
        return match ($this) {
            self::HighestResponsible => 3,
            self::Responsible => 2,
            self::Member => 1,
        };
    }

    public function isAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /**
     * 責任者以上（責任者・最高責任者）か。
     */
    public function isResponsibleOrAbove(): bool
    {
        return $this->isAtLeast(self::Responsible);
    }

    public function isHighestResponsible(): bool
    {
        return $this === self::HighestResponsible;
    }

    /**
     * 「最低1人必要」な役割か。
     */
    public function requiresAtLeastOne(): bool
    {
        return $this === self::HighestResponsible || $this === self::Responsible;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::HighestResponsible => 'bg-amber-100 text-amber-800',
            self::Responsible => 'bg-sky-100 text-sky-800',
            self::Member => 'bg-slate-100 text-slate-600',
        };
    }
}
