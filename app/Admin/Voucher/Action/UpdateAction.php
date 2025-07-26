<?php

namespace App\Admin\Voucher\Action;

use App\Models\Voucher;
use Illuminate\Support\Facades\Validator;

class UpdateAction
{
    public function handle(int $id, array $data)
    {
        $voucher = Voucher::findOrFail($id);

        $validator = Validator::make($data, [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'size' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'user_account' => 'required|string|max:255',
            'password_account' => 'required|string|max:255',
            'status' => 'in:available,sold,expired',
            'duration' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        $voucher->update($data);

        return $voucher;
    }
}
