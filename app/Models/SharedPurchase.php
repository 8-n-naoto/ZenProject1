<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SharedPurchase extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'event_circle_id', 'created_by', 'note'];
}
