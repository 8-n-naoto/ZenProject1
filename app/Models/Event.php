<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\GroupRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id', 'created_by', 'name', 'venue_name', 'venue_address',
        'description', 'image_path', 'map_image_path', 'starts_at', 'ends_at', 'fixed_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'fixed_at' => 'datetime',
            'status' => EventStatus::class,
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function days(): HasMany
    {
        return $this->hasMany(EventDay::class)->orderBy('event_date');
    }

    /**
     * イベントの参加者（event_members）。グループのメンバーとは別管理。
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_members')
            ->withPivot(['joined_at', 'budget'])
            ->withTimestamps();
    }

    public function eventCircles(): HasMany
    {
        return $this->hasMany(EventCircle::class);
    }

    public function eventProducts(): HasMany
    {
        return $this->hasMany(EventProduct::class);
    }

    public function personalPurchases(): HasMany
    {
        return $this->hasMany(PersonalPurchase::class);
    }

    public function sharedPurchases(): HasMany
    {
        return $this->hasMany(SharedPurchase::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function changeHistories(): HasMany
    {
        return $this->hasMany(ChangeHistory::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /* --------------------------------------------------------------- */
    /* 状態 */
    /* --------------------------------------------------------------- */

    public function isParticipant(User|int|null $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        if ($userId === null) {
            return false;
        }

        if ($this->relationLoaded('participants')) {
            return $this->getRelation('participants')->contains('id', $userId);
        }

        return $this->participants()->where('users.id', $userId)->exists();
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }

    public function isCompleted(): bool
    {
        return $this->status->isCompleted();
    }

    /**
     * 依頼者の役割（グループ内）。
     */
    public function roleOf(User|int|null $user): ?GroupRole
    {
        return $this->group->roleOf($user);
    }

    /**
     * 配置順に並べたサークル一覧（当日回る順に近い並び）。
     *
     * @return \Illuminate\Support\Collection<int, EventCircle>
     */
    public function circlesInBoothOrder(): \Illuminate\Support\Collection
    {
        $this->loadMissing('eventCircles');

        return $this->eventCircles
            ->sortBy(fn (EventCircle $circle) => \App\Support\BoothSorter::key($circle->booth))
            ->values();
    }

    /**
     * 開催日をまとめた表示用の文字列。
     */
    public function dateRangeLabel(): string
    {
        $days = $this->relationLoaded('days') ? $this->days : $this->days()->get();

        if ($days->isEmpty()) {
            return $this->starts_at?->format('Y/m/d') ?? '';
        }

        $first = $days->first()->event_date;
        $last = $days->last()->event_date;

        if ($days->count() === 1) {
            return $first->format('Y/m/d');
        }

        return $first->format('Y/m/d').'〜'.$last->format('m/d').'（全'.$days->count().'日）';
    }

    /**
     * 会場図（見取り図）のURL。
     */
    public function mapImageUrl(): ?string
    {
        if ($this->map_image_path === null || $this->map_image_path === '') {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->map_image_path);
    }

    public function imageUrl(): ?string
    {
        if ($this->image_path === null || $this->image_path === '') {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path);
    }

    /* --------------------------------------------------------------- */
    /* スコープ */
    /* --------------------------------------------------------------- */

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('ends_at', '>=', now())->orderBy('starts_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', EventStatus::Completed->value);
    }
}
