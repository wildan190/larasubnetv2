<?php

namespace App\Admin\Voucher\Action;

use App\Models\Voucher;

class DeleteAction
{
    public function handle(int $id)
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->delete();

        return response()->json(['message' => 'Voucher deleted successfully.']);
    }
}
