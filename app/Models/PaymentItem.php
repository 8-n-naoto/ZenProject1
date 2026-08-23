<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 支払いの内訳。どの購入結果の何点分かを記録する。
 */
class PaymentItem extends Model
{
    use HasFactory;

    protected $fillable = ['payment_id', 'purchase_result_id', 'quantity', 'amount'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'amount' => 'integer'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function purchaseResult(): BelongsTo
    {
        return $this->belongsTo(PurchaseResult::class);
    }
}
