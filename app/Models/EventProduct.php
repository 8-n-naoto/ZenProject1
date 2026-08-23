<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['event_id', 'event_circle_id', 'product_id', 'name', 'price', 'image_path', 'status'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'status' => ProductStatus::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventCircle(): BelongsTo
    {
        return $this->belongsTo(EventCircle::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function personalPurchases(): HasMany
    {
        return $this->hasMany(PersonalPurchase::class);
    }

    public function sharedPurchaseItems(): HasMany
    {
        return $this->hasMany(SharedPurchaseItem::class);
    }

    public function priceLabel(): string
    {
        return '¥'.number_format($this->price);
    }

    public function imageUrl(): ?string
    {
        if ($this->image_path === null || $this->image_path === '') {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path);
    }
}
