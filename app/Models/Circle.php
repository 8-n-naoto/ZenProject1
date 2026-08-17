<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Circle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'website_url', 'map_image_path', 'map_x', 'map_y', 'description'];
}
