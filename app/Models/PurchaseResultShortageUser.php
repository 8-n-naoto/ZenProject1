<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 購入結果が不足したときに、誰が何個受け取れなかったかの記録。
 */
class PurchaseResultShortageUser extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_result_id', 'user_id', 'shortage_quantity'];

    protected function casts(): array
    {
        return ['shortage_quantity' => 'integer'];
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
