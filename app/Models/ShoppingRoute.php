<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 当日の巡回順（手動で並べ替えた結果）。
 */
class ShoppingRoute extends Model
{
    protected $fillable = ['event_id', 'user_id', 'circle_order'];

    protected function casts(): array
    {
        return ['circle_order' => 'array'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return array<int, int>
     */
    public function order(): array
    {
        return array_map('intval', $this->getAttribute('circle_order') ?? []);
    }
}
