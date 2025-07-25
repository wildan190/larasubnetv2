<?php

namespace App\User\Purchase\Action;

use App\Models\Cart;
use App\Models\Voucher;

class CartAction
{
    public static function addToCart($user, $categoryId, $quantity)
    {
        $availableVouchers = Voucher::where('category_id', $categoryId)
            ->where('status', 'available')
            ->limit($quantity)
            ->get();

        if ($availableVouchers->count() < $quantity) {
            return ['error' => 'Not enough vouchers available'];
        }

        foreach ($availableVouchers as $voucher) {
            Cart::create([
                'user_id' => $user->id,
                'category_id' => $voucher->category_id,
                'voucher_id' => $voucher->id,
                'quantity' => 1, // always 1
            ]);

            $voucher->update(['status' => 'in_cart']);
        }

        return ['message' => 'Cart updated'];
    }

    public static function removeFromCart($user, $cartId)
    {
        $cart = Cart::where('id', $cartId)
            ->where('user_id', $user->id)
            ->first();

        if (! $cart) {
            return ['error' => 'Cart item not found'];
        }

        // Kembalikan status voucher (opsional tapi direkomendasikan)
        if ($cart->voucher) {
            $cart->voucher->update(['status' => 'available']);
        }

        $cart->delete();

        return ['message' => 'Cart item removed'];
    }
}
