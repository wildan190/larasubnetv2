<?php

namespace App\User\Balance\Action;

use App\Models\Balance;
use Midtrans\Config;

class TopupAction
{
    public static function create($user, $amount)
    {
        self::midtransConfig();

        $orderId = 'TOPUP-'.time().'-'.rand(100, 999);

        $params = [
            'payment_type' => 'bank_transfer',
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'bank_transfer' => [
                'bank' => 'bni',
            ],
        ];

        $response = \Midtrans\CoreApi::charge($params);

        $va_number = $response->va_numbers[0]->va_number ?? null;
        $vendor = $response->va_numbers[0]->bank ?? null;

        Balance::create([
            'user_id' => $user->id,
            'order_id' => $orderId,
            'amount' => $amount,
            'payment_type' => 'bank_transfer',
            'status' => 'pending',
            'va_number' => $va_number,
            'vendor' => $vendor,
        ]);

        return [
            'order_id' => $orderId,
            'va_number' => $va_number,
            'vendor' => $vendor,
            'amount' => $amount,
        ];
    }

    private static function midtransConfig()
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production', true);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }
}
