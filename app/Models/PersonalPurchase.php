<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalPurchase extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'event_product_id', 'user_id', 'planned_quantity'];
}
