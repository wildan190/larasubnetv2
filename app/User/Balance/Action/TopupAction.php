<?php

namespace App\User\Balance\Action;

use App\Models\Balance;
use Midtrans\Config;
use Midtrans\Snap;

class TopupAction
{
    public static function create($user, $amount)
    {
        self::midtransConfig();

        $orderId = 'TOPUP-' . time() . '-' . rand(100, 999);

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'callbacks' => [
                'finish' => "https://6000-firebase-studio-1752952017341.cluster-nzwlpk54dvagsxetkvxzbvslyi.cloudworkstations.dev/balance", // Sesuaikan jika Anda pakai route untuk handle redirect
            ],
        ];

        $snapToken = Snap::getSnapToken($params);

        Balance::create([
            'user_id' => $user->id,
            'order_id' => $orderId,
            'amount' => $amount,
            'payment_type' => 'snap',
            'status' => 'pending',
            'va_number' => null, 
            'vendor' => null,
        ]);

        return [
            'order_id' => $orderId,
            'snap_token' => $snapToken,
            'amount' => $amount,
        ];
    }

    private static function midtransConfig()
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }
}
