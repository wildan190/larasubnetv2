<?php

namespace App\User\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Transaction;
use App\User\Purchase\Action\CartAction;
use App\User\Purchase\Action\PurchaseAction;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function addToCart(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $result = CartAction::addToCart($user, $request->category_id, $request->quantity);

        return response()->json($result);
    }

    public function removeFromCart(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|exists:carts,id',
        ]);

        $user = $request->user();
        $result = CartAction::removeFromCart($user, $request->cart_id);

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 404);
        }

        return response()->json($result);
    }

    public function viewCart(Request $request)
    {
        $cart = Cart::with('voucher')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json(['cart' => $cart]);
    }

    public function checkout(Request $request)
    {
        $result = PurchaseAction::checkout($request->user());

        if (isset($result['error'])) {
            return response()->json(
                [
                    'message' => $result['error'],
                    'exception' => $result['exception'] ?? null,
                ],
                400,
            );
        }

        return response()->json(['message' => $result['message']]);
    }

    public function checkoutWithMidtrans(Request $request)
    {
        $result = PurchaseAction::checkoutWithMidtrans($request->user());

        if (isset($result['error'])) {
            return response()->json(
                [
                    'message' => $result['error'],
                    'exception' => $result['exception'] ?? null,
                ],
                400,
            );
        }

        return response()->json($result);
    }

    public function checkoutWithMidtransQris(Request $request)
    {
        $result = PurchaseAction::checkoutWithMidtransQris($request->user());

        if (isset($result['error'])) {
            return response()->json(
                [
                    'message' => $result['error'],
                    'exception' => $result['exception'] ?? null,
                ],
                400,
            );
        }

        return response()->json($result);
    }

    public function history(Request $request)
    {
        $user = $request->user();

        $transactions = Transaction::with(['items.voucher'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'message' => 'Purchase history retrieved successfully',
            'data' => $transactions,
        ]);
    }

    public function pendingTransactions(Request $request)
    {
        $user = $request->user();

        $pendingTransactions = Transaction::with('items.voucher')->where('user_id', $user->id)->where('status', 'pending')->orderByDesc('created_at')->get();

        return response()->json([
            'message' => 'Pending transactions retrieved successfully',
            'data' => $pendingTransactions,
        ]);
    }
}
