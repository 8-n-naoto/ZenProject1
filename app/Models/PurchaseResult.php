<?php

namespace App\Models;

use App\Enums\PurchaseResultStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'personal_purchase_id', 'shared_purchase_item_id', 'event_product_id',
        'purchase_assignee_user_id', 'planned_quantity', 'purchased_quantity', 'unit_price', 'status',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'integer',
            'purchased_quantity' => 'integer',
            'unit_price' => 'integer',
            'status' => PurchaseResultStatus::class,
        ];
    }

    public function personalPurchase(): BelongsTo
    {
        return $this->belongsTo(PersonalPurchase::class);
    }

    public function sharedPurchaseItem(): BelongsTo
    {
        return $this->belongsTo(SharedPurchaseItem::class);
    }

    public function eventProduct(): BelongsTo
    {
        return $this->belongsTo(EventProduct::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchase_assignee_user_id')->withTrashed();
    }

    public function shortageUsers(): HasMany
    {
        return $this->hasMany(PurchaseResultShortageUser::class);
    }

    public function excessTakeover(): HasOne
    {
        return $this->hasOne(ExcessTakeover::class);
    }

    public function paymentItems(): HasMany
    {
        return $this->hasMany(PaymentItem::class);
    }

    /**
     * 精算に用いる単価。未入力なら商品の設定価格を使う。
     */
    public function effectiveUnitPrice(): int
    {
        return $this->unit_price ?? (int) ($this->eventProduct?->price ?? 0);
    }

    public function totalAmount(): int
    {
        return $this->effectiveUnitPrice() * $this->purchased_quantity;
    }

    public function isShared(): bool
    {
        return $this->shared_purchase_item_id !== null;
    }
}
