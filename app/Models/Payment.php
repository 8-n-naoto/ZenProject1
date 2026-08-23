<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 実際の支払い記録。支払う側が報告し、受け取る側が確認する。
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'settlement_id', 'payer_user_id', 'payee_user_id',
        'confirmed_by', 'total_amount', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id')->withTrashed();
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payee_user_id')->withTrashed();
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PaymentItem::class);
    }

    public function amountLabel(): string
    {
        return '¥'.number_format($this->total_amount);
    }
}
