<?php

namespace App\Services;

use App\Models\Balance;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class MidtransNotificationHandler
{
    public static function handle(array $payload): void
    {
        try {
            $orderId = $payload['order_id'] ?? null;
            $statusCode = $payload['status_code'] ?? null;
            $grossAmount = $payload['gross_amount'] ?? null;
            $signatureKey = $payload['signature_key'] ?? null;
            $transactionStatus = $payload['transaction_status'] ?? null;
            $fraudStatus = $payload['fraud_status'] ?? null;
            $paymentType = $payload['payment_type'] ?? null;

            if (!$orderId || !$signatureKey || !$statusCode || !$grossAmount || !$transactionStatus) {
                Log::warning('Midtrans callback: Missing required fields', $payload);
                return;
            }

            $serverKey = config('services.midtrans.server_key');
            $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

            if ($signatureKey !== $expectedSignature) {
                Log::warning('Midtrans callback: Signature mismatch', [
                    'expected' => $expectedSignature,
                    'received' => $signatureKey,
                ]);
                return;
            }

            Log::info("Midtrans callback: Signature verified", ['order_id' => $orderId]);

            // ========================
            // === Handle Topup =======
            // ========================
            if (str_starts_with($orderId, 'TOPUP-')) {
                $balance = Balance::where('order_id', $orderId)->first();

                if (!$balance) {
                    Log::warning("Topup: Balance not found for order_id: {$orderId}");
                    return;
                }

                if ($balance->status !== 'pending') {
                    Log::info("Topup: Already processed for order_id: {$orderId}");
                    return;
                }

                if ($transactionStatus === 'settlement' || ($transactionStatus === 'capture' && $fraudStatus === 'accept')) {
                    $balance->update(['status' => 'success']);
                    $balance->user->increment('balance', $balance->amount);

                    Log::info("Topup: Success [{$orderId}]");
                } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire', 'failure'])) {
                    $balance->update(['status' => 'failed']);

                    Log::info("Topup: Failed [{$orderId}] with status {$transactionStatus}");
                } else {
                    Log::info("Topup: Ignored transaction_status '{$transactionStatus}' for order_id: {$orderId}");
                }

                return;
            }

            // =========================
            // === Handle Purchase =====
            // =========================
            if (str_starts_with($orderId, 'PURCHASE-')) {
                $transaction = Transaction::with('items.voucher')->where('order_id', $orderId)->first();

                if (!$transaction) {
                    Log::warning("Purchase: Transaction not found for order_id: {$orderId}");
                    return;
                }

                if ($transaction->status !== 'pending') {
                    Log::info("Purchase: Already processed [{$orderId}]");
                    return;
                }

                if ($transactionStatus === 'settlement' || ($transactionStatus === 'capture' && $fraudStatus === 'accept')) {
                    $transaction->update(['status' => 'success']);

                    foreach ($transaction->items as $item) {
                        $voucher = $item->voucher;
                        if ($voucher) {
                            $voucher->update([
                                'status' => 'sold',
                                'user_id' => $transaction->user_id,
                            ]);
                        }
                    }

                    Log::info("Purchase: Success [{$orderId}]");
                } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire', 'failure'])) {
                    $transaction->update(['status' => 'failed']);

                    foreach ($transaction->items as $item) {
                        $voucher = $item->voucher;
                        if ($voucher && $voucher->status === 'reserved') {
                            $voucher->update([
                                'status' => 'available',
                                'user_id' => null,
                            ]);
                        }
                    }

                    Log::info("Purchase: Failed [{$orderId}] with status {$transactionStatus}");
                } else {
                    Log::info("Purchase: Ignored transaction_status '{$transactionStatus}' for order_id: {$orderId}");
                }

                return;
            }

            Log::warning("Midtrans: Unknown order prefix for order_id: {$orderId}");

        } catch (\Throwable $e) {
            Log::error('Midtrans callback: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);
        }
    }
}
