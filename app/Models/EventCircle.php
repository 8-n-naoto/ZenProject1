<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventCircle extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'circle_id', 'display_name', 'booth', 'sellout_risk',
        'map_image_path', 'map_x', 'map_y', 'venue_map_x', 'venue_map_y',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'map_x' => 'integer',
            'map_y' => 'integer',
            'venue_map_x' => 'integer',
            'venue_map_y' => 'integer',
            'sellout_risk' => \App\Enums\SelloutRisk::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function eventProducts(): HasMany
    {
        return $this->hasMany(EventProduct::class);
    }

    public function sharedPurchase(): HasOne
    {
        return $this->hasOne(SharedPurchase::class);
    }

    public function mapImageUrl(): ?string
    {
        if ($this->map_image_path === null || $this->map_image_path === '') {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->map_image_path);
    }

    /**
     * 配置マップ上のピン位置（％）。画像とピンが両方そろっている場合だけ返す。
     *
     * @return array{x: int, y: int}|null
     */
    public function mapPin(): ?array
    {
        if ($this->mapImageUrl() === null || $this->map_x === null || $this->map_y === null) {
            return null;
        }

        return ['x' => $this->map_x, 'y' => $this->map_y];
    }

    /**
     * 会場図上のピン位置（％）。イベントに会場図があり、位置が入っている場合だけ返す。
     *
     * @return array{x: int, y: int}|null
     */
    public function venueMapPin(): ?array
    {
        if ($this->venue_map_x === null || $this->venue_map_y === null) {
            return null;
        }

        return ['x' => $this->venue_map_x, 'y' => $this->venue_map_y];
    }

    public function locationLabel(): string
    {
        return $this->booth !== null && $this->booth !== '' ? $this->booth : '配置未定';
    }
}
