<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseResult extends Model
{
    use HasFactory;

    protected $fillable = ['personal_purchase_id', 'shared_purchase_item_id', 'event_product_id', 'purchase_assignee_user_id', 'planned_quantity', 'purchased_quantity', 'unit_price', 'status'];
}
