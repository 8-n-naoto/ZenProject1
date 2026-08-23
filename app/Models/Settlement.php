<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 相殺後の送金1件。「誰が誰にいくら払うか」を表す。
 */
class Settlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'payer_user_id', 'payee_user_id', 'amount', 'status', 'settled_at', 'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => SettlementStatus::class,
            'settled_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id')->withTrashed();
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payee_user_id')->withTrashed();
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by')->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === SettlementStatus::Completed;
    }

    public function amountLabel(): string
    {
        return '¥'.number_format($this->amount);
    }

    /**
     * 受取確認待ちの支払い報告。
     *
     * payments を読み込み済みの場合はそれを使う（一覧でのN+1を避けるため）。
     */
    public function reportedPayment(): ?Payment
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments
                ->where('status', \App\Enums\PaymentStatus::Reported)
                ->sortByDesc('id')
                ->first();
        }

        return $this->payments()
            ->where('status', \App\Enums\PaymentStatus::Reported->value)
            ->latest('id')
            ->first();
    }
}
