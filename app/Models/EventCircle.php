<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventCircle extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'circle_id', 'display_name', 'booth', 'map_image_path', 'map_x', 'map_y'];
}
