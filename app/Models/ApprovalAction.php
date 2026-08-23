<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 承認申請に対する1人分の賛否。
 */
class ApprovalAction extends Model
{
    use HasFactory;

    public const APPROVE = 'approve';

    public const REJECT = 'reject';

    protected $fillable = ['approval_id', 'actor_user_id', 'action', 'acted_at'];

    protected function casts(): array
    {
        return ['acted_at' => 'datetime'];
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }

    public function isApprove(): bool
    {
        return $this->action === self::APPROVE;
    }
}
