<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SharedPurchaseItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['shared_purchase_id', 'event_product_id', 'planned_quantity'];
}
