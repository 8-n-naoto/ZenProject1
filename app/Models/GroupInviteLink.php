<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * グループへの招待リンク（合い言葉）。
 */
class GroupInviteLink extends Model
{
    use HasFactory;

    protected $fillable = ['group_id', 'created_by', 'token', 'used_count', 'max_uses', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'used_count' => 'integer',
            'max_uses' => 'integer',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * 推測されにくく、口頭でも伝えられる長さのトークンを作る。
     */
    public static function generateToken(): string
    {
        return Str::lower(Str::random(24));
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsedUp(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired() && ! $this->isUsedUp();
    }

    /**
     * 使えない理由（使える場合は null）。
     */
    public function unusableReason(): ?string
    {
        return match (true) {
            $this->isRevoked() => 'この招待リンクは無効にされています。',
            $this->isExpired() => 'この招待リンクは期限が切れています。',
            $this->isUsedUp() => 'この招待リンクは使用回数の上限に達しています。',
            default => null,
        };
    }

    public function url(): string
    {
        return route('join.show', $this->token);
    }

    /**
     * 残り何回使えるか（無制限なら null）。
     */
    public function remainingUses(): ?int
    {
        return $this->max_uses === null ? null : max(0, $this->max_uses - $this->used_count);
    }
}
