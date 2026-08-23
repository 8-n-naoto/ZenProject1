<?php

namespace App\Models;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 重要操作の承認申請。責任者以上の過半数で可決する。
 */
class Approval extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'event_id', 'applicant_user_id', 'approvable_type', 'approvable_id',
        'action_type', 'status', 'submitted_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'action_type' => ApprovalActionType::class,
            'status' => ApprovalStatus::class,
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id')->withTrashed();
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class);
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending;
    }

    public function approvalCount(): int
    {
        return $this->actions()->where('action', 'approve')->count();
    }

    public function rejectionCount(): int
    {
        return $this->actions()->where('action', 'reject')->count();
    }

    public function hasVoted(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->actions()->where('actor_user_id', $userId)->exists();
    }
}
