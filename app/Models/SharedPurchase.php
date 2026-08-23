<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharedPurchase extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'event_circle_id', 'created_by', 'note'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventCircle(): BelongsTo
    {
        return $this->belongsTo(EventCircle::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(SharedPurchaseItem::class);
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(CirclePurchaseAssignee::class);
    }

    public function confirmedAssignees(): HasMany
    {
        return $this->assignees()->whereNotNull('confirmed_at');
    }

    public function candidateAssignees(): HasMany
    {
        return $this->assignees()->whereNull('confirmed_at');
    }

    public function hasConfirmedAssignee(): bool
    {
        return $this->confirmedAssignees()->exists();
    }

    /**
     * 予定金額の合計（円）。
     */
    public function plannedAmount(): int
    {
        $this->loadMissing('items.eventProduct');

        return $this->items->sum(fn (SharedPurchaseItem $item) => $item->plannedAmount());
    }

    public function plannedQuantity(): int
    {
        $this->loadMissing('items');

        return (int) $this->items->sum('planned_quantity');
    }
}
