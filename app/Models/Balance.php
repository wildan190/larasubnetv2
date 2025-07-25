<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Balance extends Model
{
    protected $fillable = [
        'user_id', 'amount', 'status', 'order_id', 'payment_type', 'va_number', 'vendor',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
