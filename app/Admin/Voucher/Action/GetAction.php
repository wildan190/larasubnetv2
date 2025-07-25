<?php

namespace App\Admin\Voucher\Action;

use App\Models\Voucher;

class GetAction
{
    public function handle()
    {
        return Voucher::with('category')->get();
    }
}
