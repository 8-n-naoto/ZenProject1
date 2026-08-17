<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'payer_user_id', 'payee_user_id', 'confirmed_by', 'total_amount', 'status', 'paid_at'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }
}
