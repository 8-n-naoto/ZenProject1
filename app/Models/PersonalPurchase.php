<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PersonalPurchase extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'event_product_id', 'user_id', 'planned_quantity'];

    protected function casts(): array
    {
        return ['planned_quantity' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventProduct(): BelongsTo
    {
        return $this->belongsTo(EventProduct::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function purchaseResult(): HasOne
    {
        return $this->hasOne(PurchaseResult::class);
    }

    /**
     * 予定金額（円）。
     */
    public function plannedAmount(): int
    {
        $this->loadMissing('eventProduct');

        return $this->planned_quantity * (int) ($this->eventProduct?->price ?? 0);
    }
}
