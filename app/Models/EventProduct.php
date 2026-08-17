<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['event_id', 'event_circle_id', 'product_id', 'name', 'price', 'image_path', 'status'];
}
