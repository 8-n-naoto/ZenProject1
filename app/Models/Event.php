<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['group_id', 'created_by', 'name', 'venue_name', 'venue_address', 'description', 'image_path', 'starts_at', 'ends_at', 'fixed_at', 'status'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'fixed_at' => 'datetime'];
    }
}
