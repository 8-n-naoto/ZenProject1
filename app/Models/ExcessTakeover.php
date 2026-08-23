<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 予定より多く購入してしまった分を誰が引き取るかの記録。
 */
class ExcessTakeover extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_result_id', 'user_id', 'takeover_quantity'];

    protected function casts(): array
    {
        return ['takeover_quantity' => 'integer'];
    }

    public function purchaseResult(): BelongsTo
    {
        return $this->belongsTo(PurchaseResult::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
