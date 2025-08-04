<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminHistoryTransactionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $transactions = Transaction::with(['items.voucher'])
                ->orderByDesc('created_at')
                ->get();

            if ($transactions->isEmpty()) {
                return response()->json(
                    [
                        'message' => 'No transactions found',
                        'data' => [],
                    ],
                    404,
                );
            }

            // For debugging
            Log::info('Transactions:', $transactions->toArray());

            return response()->json([
                'message' => 'Purchase history retrieved successfully',
                'data' => $transactions,
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    'message' => 'Error retrieving transactions',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function pendingHistory(Request $request)
    {
        $pendingTransactions = Transaction::with('items.voucher')->where('user_id')->where('status', 'pending')->orderByDesc('created_at')->get();

        return response()->json([
            'message' => 'Pending transactions retrieved successfully',
            'data' => $pendingTransactions,
        ]);
    }

    public function showTransactionItemsHistory(Request $request)
    {
        try {
            $transactionItems = TransactionItem::with(['voucher', 'transaction'])
                ->orderByDesc('created_at')
                ->get();

            if ($transactionItems->isEmpty()) {
                return response()->json([
                    'message' => 'No transaction items found',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'message' => 'Transaction items retrieved successfully',
                'data' => $transactionItems
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving transaction items',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
