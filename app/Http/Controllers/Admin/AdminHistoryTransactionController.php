<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class AdminHistoryTransactionController extends Controller
{
    public function index(Request $request)
    {
        $transactions = Transaction::with(['items.voucher'])
            ->where('user_id')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'message' => 'Purchase history retrieved successfully',
            'data' => $transactions,
        ]);
    }

    public function pendingHistory(Request $request)
    {

        $pendingTransactions = Transaction::with('items.voucher')->where('user_id')->where('status', 'pending')->orderByDesc('created_at')->get();

        return response()->json([
            'message' => 'Pending transactions retrieved successfully',
            'data' => $pendingTransactions,
        ]);
    }
}
