<?php

namespace App\User\Purchase\Action;

use App\Models\Cart;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Midtrans\Config;
use Midtrans\CoreApi;
use Midtrans\Snap;

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

            if (! $voucher || $voucher->status !== 'in_cart') {
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
                'order_id' => 'PURCHASE-'.time().'-'.rand(1000, 9999),
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
        // 1. Get cart items along with their voucher details
        // Assumption: Each item in `carts` refers to a unique `voucher_id`
        $cartItems = Cart::with('voucher')->where('user_id', $user->id)->get();

        if ($cartItems->isEmpty()) {
            return ['error' => 'Keranjang kosong.'];
        }

        $totalAmount = 0;
        $vouchersToProcess = []; // Will contain the actual voucher objects to be processed

        foreach ($cartItems as $cart) {
            $voucher = $cart->voucher;

            // 2. Validate Voucher
            // Ensure the voucher exists and its status is 'in_cart'
            // 'in_cart' status indicates the voucher is in the cart and not yet sold/reserved
            if (! $voucher) {
                return ['error' => 'Voucher untuk satu atau lebih item di keranjang tidak ditemukan.'];
            }

            if ($voucher->status !== 'in_cart') {
                return ['error' => "Voucher '{$voucher->name}' tidak tersedia. Status: {$voucher->status}."];
            }

            // Assume each `cart` item represents 1 unique voucher unit
            $totalAmount += $voucher->price;
            $vouchersToProcess[] = $voucher; // Add the complete voucher object to the list
        }

        // Generate Order ID
        $orderId = 'PURCHASE-'.time().'-'.rand(1000, 9999);

        // --- Midtrans Configuration ---
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production', true); // Ensure this is true in production
        Config::$isSanitized = true;
        Config::$is3ds = true; // Use 3DS for credit card payments if desired (not directly for VA, but good practice for Core API)

        // --- Midtrans Core API Parameters ---
        $params = [
            'payment_type' => 'bank_transfer', // As per your original Core API request
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $totalAmount,
            ],
            // Item details for Midtrans. Each unique voucher becomes 1 item.
            'item_details' => array_map(function ($voucher) {
                return [
                    'id' => (string) $voucher->id, // Unique voucher ID
                    'price' => $voucher->price,
                    'quantity' => 1, // Quantity is always 1 for each unique voucher
                    'name' => $voucher->name ?? 'Voucher',
                ];
            }, $vouchersToProcess),
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '', // Use null coalescing for optional phone
            ],
            'bank_transfer' => [
                'bank' => 'bni', // Specify the bank for direct VA (e.g., 'bca', 'bri', 'permata')
            ],
        ];

        DB::beginTransaction();
        try {
            // Charge the transaction using Midtrans Core API
            $response = CoreApi::charge($params);

            // Extract relevant information from Midtrans response
            $va_number = $response->va_numbers[0]->va_number ?? null;
            $vendor = $response->va_numbers[0]->bank ?? null; // e.g., 'bni'

            // 3. Create Transaction in Database with 'pending' status
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'order_id' => $orderId,
                'total' => $totalAmount,
                'payment_method' => 'midtrans_bank_transfer', // More specific payment method
                'status' => 'pending', // Initial status is pending
                'va_number' => $va_number,
                'vendor' => $vendor,
            ]);

            // 4. Save transaction items and update voucher status to 'reserved'
            // 'reserved' means the voucher is booked and awaiting payment
            foreach ($vouchersToProcess as $voucher) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'voucher_id' => $voucher->id,
                    'quantity' => 1, // Quantity 1 per unique voucher
                    'price' => $voucher->price,
                ]);

                // Update voucher status to 'reserved'
                // This prevents the voucher from being purchased by another user while this transaction is pending
                $voucher->update([
                    'status' => 'reserved',
                    // user_id is NOT updated here, as ownership isn't confirmed yet
                    // user_id will be updated when the transaction status becomes 'success'
                ]);
            }

            // 5. Clear items from the cart after successfully creating the transaction
            // The cart is emptied because items have moved into a pending transaction
            Cart::where('user_id', $user->id)->delete();

            DB::commit();

            return [
                'message' => 'Transaksi berhasil dibuat. Lanjutkan pembayaran melalui Virtual Account.',
                'midtrans_response' => $response, // Return full response for frontend to display details
                'order_id' => $orderId,
                'va_number' => $va_number,
                'vendor' => $vendor,
                'amount' => $totalAmount,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'error' => 'Terjadi kesalahan saat memulai transaksi Midtrans.',
                'exception' => $e->getMessage(),
                // For debugging, you might want to return $e->getTraceAsString()
            ];
        }
    }

    public static function checkoutWithMidtransQris($user)
    {
        // 1. Ambil item keranjang beserta detail vouchernya
        // Asumsi: Setiap item di `carts` merujuk ke `voucher_id` yang unik
        $cartItems = Cart::with('voucher')->where('user_id', $user->id)->get();

        if ($cartItems->isEmpty()) {
            return ['error' => 'Keranjang kosong.'];
        }

        $totalAmount = 0;
        $vouchersToProcess = []; // Akan berisi objek voucher yang akan diproses

        foreach ($cartItems as $cart) {
            $voucher = $cart->voucher;

            // 2. Validasi Voucher
            // Memastikan voucher ada dan statusnya 'in_cart'
            // Status 'in_cart' mengindikasikan voucher sedang di keranjang dan belum terjual/reservasi
            if (! $voucher) {
                return ['error' => 'Voucher untuk satu atau lebih item di keranjang tidak ditemukan.'];
            }

            // Ini penting: Pastikan status voucher adalah 'in_cart' sebelum memprosesnya
            if ($voucher->status !== 'in_cart') {
                return ['error' => "Voucher '{$voucher->name}' tidak tersedia. Status: {$voucher->status}."];
            }

            // Asumsi setiap `cart` item mewakili 1 unit voucher unik
            $totalAmount += $voucher->price;
            $vouchersToProcess[] = $voucher; // Tambahkan objek voucher lengkap ke list
        }

        // Generate Order ID
        $orderId = 'PURCHASE-'.time().'-'.rand(1000, 9999);

        // --- Midtrans Configuration ---
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production', true); // Pastikan ini true di produksi
        Config::$isSanitized = true;
        Config::$is3ds = false;

        // --- Midtrans Snap Parameters ---
        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $totalAmount,
            ],
            // Item details untuk Midtrans. Setiap voucher unik menjadi 1 item.
            'item_details' => array_map(function ($voucher) {
                return [
                    'id' => (string) $voucher->id, // ID voucher unik
                    'price' => $voucher->price,
                    'quantity' => 1, // Kuantitas selalu 1 untuk setiap voucher unik
                    'name' => $voucher->name ?? 'Voucher',
                ];
            }, $vouchersToProcess),
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ],
            // 'enabled_payments' => ['qris'], // Opsional: Hanya tampilkan QRIS
        ];

        DB::beginTransaction();
        try {
            // Dapatkan Snap Token dari Midtrans
            $snapToken = Snap::getSnapToken($params);

            // 3. Buat Transaksi di Database dengan status 'pending'
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'order_id' => $orderId,
                'total' => $totalAmount,
                'payment_method' => 'midtrans_snap', // Metode pembayaran melalui Snap
                'status' => 'pending', // Status awal transaksi adalah pending
                'vendor' => 'midtrans_snap',
            ]);

            // 4. Simpan item transaksi dan update status voucher menjadi 'reserved'
            // 'reserved' berarti voucher sudah dibooking dan sedang menunggu pembayaran
            foreach ($vouchersToProcess as $voucher) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'voucher_id' => $voucher->id,
                    'quantity' => 1, // Kuantitas 1 per voucher unik
                    'price' => $voucher->price,
                ]);

                // Perbarui status voucher menjadi 'reserved'
                // Ini mencegah voucher dibeli oleh user lain saat transaksi ini masih pending
                $voucher->update([
                    'status' => 'reserved',
                    // user_id tidak diupdate di sini, karena belum tentu jadi milik user
                    // user_id akan diupdate saat status transaksi menjadi 'success'
                ]);
            }

            // 5. Hapus item dari keranjang setelah berhasil membuat transaksi
            // Keranjang dikosongkan karena item sudah masuk ke transaksi pending
            Cart::where('user_id', $user->id)->delete();

            DB::commit();

            return [
                'message' => 'Silakan lanjutkan pembayaran melalui Midtrans Snap.',
                'snap_token' => $snapToken, // Token ini akan digunakan di frontend
                'order_id' => $orderId,
                'amount' => $totalAmount,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'error' => 'Terjadi kesalahan saat memulai transaksi.',
                'exception' => $e->getMessage(),
            ];
        }
    }

    // public static function checkoutWithMidtransQris($user)
    // {
    //     $carts = Cart::where('user_id', $user->id)->get();
    //     if ($carts->isEmpty()) {
    //         return ['error' => 'Cart is empty'];
    //     }

    //     $total = 0;
    //     $voucherList = [];

    //     foreach ($carts as $cart) {
    //         $vouchers = Voucher::where('category_id', $cart->category_id)->where('status', 'available')->limit($cart->quantity)->get();

    //         if ($vouchers->count() < $cart->quantity) {
    //             return ['error' => 'Not enough vouchers'];
    //         }

    //         foreach ($vouchers as $voucher) {
    //             $total += $voucher->price;
    //             $voucherList[] = $voucher;
    //         }
    //     }

    //     $orderId = 'PURCHASE-' . time() . '-' . rand(1000, 9999);

    //     // Midtrans Config
    //     Config::$serverKey = config('services.midtrans.server_key');
    //     Config::$isProduction = config('services.midtrans.is_production', true);
    //     Config::$isSanitized = true;
    //     Config::$is3ds = false; // tidak perlu untuk QRIS

    //     // Parameter khusus OpenAPI / SNAP-based QRIS
    //     $params = [
    //         'payment_type' => 'qris',
    //         'transaction_details' => [
    //             'order_id' => $orderId,
    //             'gross_amount' => $total,
    //         ],
    //         'item_details' => array_map(function ($v) {
    //             return [
    //                 'id' => (string) $v->id,
    //                 'price' => $v->price,
    //                 'quantity' => 1,
    //                 'name' => $v->name ?? 'Voucher',
    //             ];
    //         }, $voucherList),
    //         'customer_details' => [
    //             'first_name' => $user->name,
    //             'email' => $user->email,
    //             'phone' => $user->phone ?? '',
    //         ],
    //         'qris' => [
    //             'acquirer' => 'gopay', // gunakan 'gopay' atau 'shopeepay' sesuai akun Anda
    //         ],
    //     ];

    //     try {
    //         $response = CoreApi::charge($params);

    //         // Ambil URL gambar QR untuk discan
    //         $qrUrl = $response->actions[0]->url ?? null;

    //         DB::beginTransaction();

    //         $transaction = Transaction::create([
    //             'user_id' => $user->id,
    //             'order_id' => $orderId,
    //             'total' => $total,
    //             'payment_method' => 'midtrans_qris',
    //             'status' => 'pending',
    //             'vendor' => 'qris',
    //         ]);

    //         foreach ($voucherList as $voucher) {
    //             TransactionItem::create([
    //                 'transaction_id' => $transaction->id,
    //                 'voucher_id' => $voucher->id,
    //                 'quantity' => 1,
    //                 'price' => $voucher->price,
    //             ]);
    //             $voucher->update(['status' => 'reserved']);
    //         }

    //         Cart::where('user_id', $user->id)->delete();
    //         DB::commit();

    //         return [
    //             'message' => 'Scan QRIS dibawah untuk bayar',
    //             'midtrans_response' => $response,
    //             'order_id' => $orderId,
    //             'qris_url' => $qrUrl,
    //             'amount' => $total,
    //         ];
    //     } catch (\Throwable $e) {
    //         DB::rollBack();
    //         return [
    //             'error' => 'Transaction failed',
    //             'exception' => $e->getMessage(),
    //         ];
    //     }
    // }
}
