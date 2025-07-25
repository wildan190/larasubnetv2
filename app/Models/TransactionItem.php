<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = ['transaction_id', 'voucher_id', 'quantity', 'price'];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
