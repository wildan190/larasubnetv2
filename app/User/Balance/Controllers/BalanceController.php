<?php

namespace App\User\Balance\Controllers;

use App\Http\Controllers\Controller;
use App\Services\MidtransNotificationHandler;
use App\User\Balance\Action\TopupAction;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    public function getBalance(Request $request)
    {
        return response()->json([
            'balance' => $request->user()->balance,
        ]);
    }

    public function topup(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000',
        ]);

        $user = $request->user();
        $result = TopupAction::create($user, $request->amount);

        return response()->json([
            'message' => 'Topup created',
            'data' => $result,
        ]);
    }

    public function handle(Request $request)
    {
        MidtransNotificationHandler::handle($request->all());

        return response()->json(['message' => 'Callback handled']);
    }

    public function history(Request $request)
    {
        $user = $request->user();

        $history = $user
            ->balances()
            ->orderBy('created_at', 'desc')
            ->get(['order_id', 'amount', 'status', 'payment_type', 'va_number', 'vendor', 'created_at']);

        return response()->json([
            'history' => $history,
        ]);
    }
}
