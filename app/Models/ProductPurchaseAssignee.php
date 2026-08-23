<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商品単位の購入担当者。1つのサークルを複数人で分担する場合に使う。
 */
class ProductPurchaseAssignee extends Model
{
    use HasFactory;

    protected $fillable = ['shared_purchase_item_id', 'user_id', 'assigned_quantity'];

    protected function casts(): array
    {
        return ['assigned_quantity' => 'integer'];
    }

    public function sharedPurchaseItem(): BelongsTo
    {
        return $this->belongsTo(SharedPurchaseItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
