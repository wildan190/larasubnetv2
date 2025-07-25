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

            if (! $orderId || ! $signatureKey) {
                Log::warning('Midtrans callback: Missing order_id or signature', compact('orderId', 'signatureKey'));

                return;
            }

            $serverKey = config('services.midtrans.server_key');
            $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

            if ($signatureKey !== $expectedSignature) {
                Log::warning('Midtrans callback: Signature mismatch', [
                    'expected' => $expectedSignature,
                    'received' => $signatureKey,
                ]);

                return;
            }

            Log::info('Midtrans callback: Signature verified', ['order_id' => $orderId]);

            if (str_starts_with($orderId, 'TOPUP-')) {
                $balance = Balance::where('order_id', $orderId)->first();

                if (! $balance) {
                    Log::warning("Topup: Balance not found [{$orderId}]");

                    return;
                }

                if ($balance->status !== 'pending') {
                    Log::info("Topup: Already processed [{$orderId}]");

                    return;
                }

                if ($transactionStatus === 'settlement') {
                    $balance->update(['status' => 'success']);
                    $balance->user->increment('balance', $balance->amount);
                    Log::info("Topup: Success [{$orderId}]");
                } elseif (in_array($transactionStatus, ['cancel', 'expire', 'deny'])) {
                    $balance->update(['status' => 'failed']);
                    Log::info("Topup: Failed [{$orderId}]");
                } else {
                    Log::info("Topup: Ignored status '{$transactionStatus}' for [{$orderId}]");
                }

                return;
            }

            if (str_starts_with($orderId, 'PURCHASE-')) {
                $transaction = Transaction::with('items.voucher')->where('order_id', $orderId)->first();

                if (! $transaction) {
                    Log::warning("Purchase: Transaction not found [{$orderId}]");

                    return;
                }

                if ($transaction->status !== 'pending') {
                    Log::info("Purchase: Already processed [{$orderId}]");

                    return;
                }

                if ($transactionStatus === 'settlement') {
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

                } elseif (in_array($transactionStatus, ['cancel', 'expire', 'deny'])) {
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

                    Log::info("Purchase: Failed [{$orderId}]");
                } else {
                    Log::info("Purchase: Ignored status '{$transactionStatus}' for [{$orderId}]");
                }

                return;
            }

            Log::warning("Midtrans: Unknown order prefix for [{$orderId}]");

        } catch (\Throwable $e) {
            Log::error('Midtrans callback: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);
        }
    }
}
