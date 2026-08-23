<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * サークル単位の購入担当者。
 *
 * confirmed_at が NULL なら立候補中、値があれば確定した担当者。
 */
class CirclePurchaseAssignee extends Model
{
    use HasFactory;

    protected $fillable = ['shared_purchase_id', 'user_id', 'assigned_quantity', 'confirmed_at', 'assigned_by'];

    protected function casts(): array
    {
        return [
            'assigned_quantity' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    public function sharedPurchase(): BelongsTo
    {
        return $this->belongsTo(SharedPurchase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')->withTrashed();
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
