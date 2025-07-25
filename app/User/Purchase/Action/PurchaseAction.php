<?php

namespace App\User\Purchase\Action;

use App\Models\Cart;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Midtrans\Config;
use Midtrans\CoreApi;

class PurchaseAction
{
    public static function checkout($user)
    {
        $cartItems = Cart::with('voucher')->where('user_id', $user->id)->get();

        if ($cartItems->isEmpty()) {
            return ['error' => 'Cart is empty'];
        }

        $total = 0;
        $voucherList = [];

        foreach ($cartItems as $cart) {
            $voucher = $cart->voucher;

            if (!$voucher || $voucher->status !== 'in_cart') {
                return ['error' => 'One or more vouchers are no longer available'];
            }

            $total += $voucher->price;
            $voucherList[] = $voucher;
        }

        if ($user->balance < $total) {
            return ['error' => 'Insufficient balance'];
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'total' => $total,
                'status' => 'success',
                'payment_method' => 'balance',
                'order_id' => 'PURCHASE-' . time() . '-' . rand(1000, 9999),
            ]);

            foreach ($voucherList as $voucher) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'voucher_id' => $voucher->id,
                    'quantity' => 1,
                    'price' => $voucher->price,
                ]);

                $voucher->update([
                    'status' => 'sold',
                    'user_id' => $user->id,
                ]);
            }

            $user->decrement('balance', $total);

            Cart::where('user_id', $user->id)->delete();

            DB::commit();

            return [
                'message' => 'Purchase successful',
                'transaction_id' => $transaction->id,
                'total' => $total,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['error' => 'Transaction failed', 'exception' => $e->getMessage()];
        }
    }

    public static function checkoutWithMidtrans($user)
    {
        $carts = Cart::where('user_id', $user->id)->get();
        if ($carts->isEmpty()) {
            return ['error' => 'Cart is empty'];
        }

        $total = 0;
        $voucherList = [];

        foreach ($carts as $cart) {
            $vouchers = Voucher::where('category_id', $cart->category_id)->where('status', 'available')->limit($cart->quantity)->get();

            if ($vouchers->count() < $cart->quantity) {
                return ['error' => 'Not enough vouchers'];
            }

            foreach ($vouchers as $voucher) {
                $total += $voucher->price;
                $voucherList[] = $voucher;
            }
        }

        $orderId = 'PURCHASE-' . time() . '-' . rand(1000, 9999);

        // Midtrans config
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production', true);
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $params = [
            'payment_type' => 'bank_transfer',
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $total,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'bank_transfer' => [
                'bank' => 'bni',
            ],
        ];

        $response = CoreApi::charge($params);

        $va_number = $response->va_numbers[0]->va_number ?? null;
        $vendor = $response->va_numbers[0]->bank ?? null;

        DB::beginTransaction();
        try {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'order_id' => $orderId,
                'total' => $total,
                'payment_method' => 'midtrans',
                'status' => 'pending',
                'va_number' => $va_number,
                'vendor' => $vendor,
            ]);

            foreach ($voucherList as $voucher) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'voucher_id' => $voucher->id,
                    'quantity' => 1,
                    'price' => $voucher->price,
                ]);

                $voucher->update([
                    'status' => 'reserved',
                ]);
            }

            Cart::where('user_id', $user->id)->delete();

            DB::commit();

            return [
                'message' => 'Redirect user to complete payment',
                'midtrans_response' => $response,
                'order_id' => $orderId,
                'va_number' => $va_number,
                'vendor' => $vendor,
                'amount' => $total,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['error' => 'Transaction failed', 'exception' => $e->getMessage()];
        }
    }

    public static function checkoutWithMidtransQris($user)
    {
        $carts = Cart::where('user_id', $user->id)->get();
        if ($carts->isEmpty()) {
            return ['error' => 'Cart is empty'];
        }

        $total = 0;
        $voucherList = [];

        foreach ($carts as $cart) {
            $vouchers = Voucher::where('category_id', $cart->category_id)->where('status', 'available')->limit($cart->quantity)->get();

            if ($vouchers->count() < $cart->quantity) {
                return ['error' => 'Not enough vouchers'];
            }

            foreach ($vouchers as $voucher) {
                $total += $voucher->price;
                $voucherList[] = $voucher;
            }
        }

        $orderId = 'PURCHASE-' . time() . '-' . rand(1000, 9999);

        // Midtrans Config
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production', true);
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $params = [
            'payment_type' => 'gopay',
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $total,
            ],
            'gopay' => [
                'enable_callback' => false,
                'callback_url' => '',
            ],
        ];

        $response = CoreApi::charge($params);

        $qr_url = $response->actions[0]->url ?? null;

        DB::beginTransaction();
        try {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'order_id' => $orderId,
                'total' => $total,
                'payment_method' => 'midtrans_qris',
                'status' => 'pending',
                'va_number' => null,
                'vendor' => 'gopay_qris',
            ]);

            foreach ($voucherList as $voucher) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'voucher_id' => $voucher->id,
                    'quantity' => 1,
                    'price' => $voucher->price,
                ]);

                $voucher->update(['status' => 'reserved']);
            }

            Cart::where('user_id', $user->id)->delete();

            DB::commit();

            return [
                'message' => 'Scan QR to complete payment',
                'midtrans_response' => $response,
                'order_id' => $orderId,
                'qris_url' => $qr_url,
                'amount' => $total,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['error' => 'Transaction failed', 'exception' => $e->getMessage()];
        }
    }
}
