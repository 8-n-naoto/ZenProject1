<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SharedPurchaseItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['shared_purchase_id', 'event_product_id', 'planned_quantity'];

    protected function casts(): array
    {
        return ['planned_quantity' => 'integer'];
    }

    public function sharedPurchase(): BelongsTo
    {
        return $this->belongsTo(SharedPurchase::class);
    }

    public function eventProduct(): BelongsTo
    {
        return $this->belongsTo(EventProduct::class);
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(ProductPurchaseAssignee::class);
    }

    public function purchaseResult(): HasOne
    {
        return $this->hasOne(PurchaseResult::class);
    }

    public function plannedAmount(): int
    {
        $this->loadMissing('eventProduct');

        return $this->planned_quantity * (int) ($this->eventProduct?->price ?? 0);
    }
}
