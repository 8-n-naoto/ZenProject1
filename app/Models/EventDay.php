<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventDay extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'event_date', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function timeLabel(): string
    {
        if ($this->starts_at === null && $this->ends_at === null) {
            return '時間未定';
        }

        return ($this->starts_at?->format('H:i') ?? '').'〜'.($this->ends_at?->format('H:i') ?? '');
    }
}
