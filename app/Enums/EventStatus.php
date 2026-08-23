<?php

namespace App\Enums;

/**
 * イベントの状態。
 *
 * 準備中 → 受付中 → 確定済 → 開催中 → 精算中 → 完了
 */
enum EventStatus: string
{
    case Preparation = 'preparation';
    case Accepting = 'accepting';
    case Fixed = 'fixed';
    case Ongoing = 'ongoing';
    case Settling = 'settling';
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
            self::Preparation => '準備中',
            self::Accepting => '受付中',
            self::Fixed => '確定済',
            self::Ongoing => '開催中',
            self::Settling => '精算中',
            self::Completed => '完了',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::Preparation => 1,
            self::Accepting => 2,
            self::Fixed => 3,
            self::Ongoing => 4,
            self::Settling => 5,
            self::Completed => 6,
        };
    }

    /**
     * 次に進める状態。完了の次はない。
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Preparation => self::Accepting,
            self::Accepting => self::Fixed,
            self::Fixed => self::Ongoing,
            self::Ongoing => self::Settling,
            self::Settling => self::Completed,
            self::Completed => null,
        };
    }

    /**
     * 内容が確定してロックされているか（確定済以降）。
     */
    public function isLocked(): bool
    {
        return $this->order() >= self::Fixed->order();
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    /**
     * 購入希望を受け付けられるか。
     */
    public function acceptsPurchaseRequests(): bool
    {
        return $this === self::Accepting;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Preparation => 'bg-slate-100 text-slate-600',
            self::Accepting => 'bg-emerald-100 text-emerald-800',
            self::Fixed => 'bg-sky-100 text-sky-800',
            self::Ongoing => 'bg-indigo-100 text-indigo-800',
            self::Settling => 'bg-amber-100 text-amber-800',
            self::Completed => 'bg-slate-200 text-slate-700',
        };
    }
}
