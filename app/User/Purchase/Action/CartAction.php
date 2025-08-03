<?php

namespace App\User\Purchase\Action;

use App\Models\Cart;
use App\Models\Voucher;

class CartAction
{
    /**
     * Menambahkan voucher spesifik ke keranjang belanja.
     *
     * @param  \App\Models\User  $user  Pengguna yang menambahkan item ke keranjang.
     * @param  int  $voucherId  ID dari voucher yang dipilih secara spesifik oleh pengguna.
     * @param  int  $quantity  Kuantitas voucher yang ingin ditambahkan (biasanya 1 untuk voucher unik).
     * @return array Respon pesan sukses atau error.
     */
    public static function addToCart($user, $voucherId, $quantity = 1)
    {
        // 1. Cari voucher berdasarkan ID yang dipilih
        $voucher = Voucher::where('id', $voucherId)
            ->where('status', 'available')
            ->first();

        // 2. Periksa apakah voucher ditemukan dan tersedia
        if (! $voucher) {
            return ['error' => 'Voucher tidak ditemukan atau tidak tersedia.'];
        }

        // 3. Periksa apakah voucher sudah ada di keranjang untuk pengguna ini
        $existingCartItem = Cart::where('user_id', $user->id)
            ->where('voucher_id', $voucher->id)
            ->first();

        if ($existingCartItem) {
            // Jika voucher sudah ada di keranjang, Anda bisa memilih untuk:
            // a) Mengembalikan error (voucher ini sudah ada di keranjang)
            return ['error' => 'Voucher ini sudah ada di keranjang Anda.'];
            // b) Mengupdate kuantitas (jika voucher bisa ditambahkan lebih dari 1,
            //    tapi skenario Anda menyiratkan voucher unik)
            // $existingCartItem->update(['quantity' => $existingCartItem->quantity + $quantity]);
            // return ['message' => 'Kuantitas voucher di keranjang diperbarui.'];
        }

        // 4. Buat item keranjang baru
        Cart::create([
            'user_id' => $user->id,
            'category_id' => $voucher->category_id, // Tetap simpan category_id untuk referensi
            'voucher_id' => $voucher->id,
            'quantity' => $quantity, // Kuantitas sesuai permintaan user (biasanya 1 untuk voucher)
        ]);

        // 5. Update status voucher menjadi 'in_cart'
        $voucher->update(['status' => 'in_cart']);

        return ['message' => 'Voucher berhasil ditambahkan ke keranjang.'];
    }

    /**
     * Menghapus item dari keranjang dan mengembalikan status voucher.
     *
     * @param  \App\Models\User  $user  Pengguna yang menghapus item dari keranjang.
     * @param  int  $cartId  ID dari item keranjang yang ingin dihapus.
     * @return array Respon pesan sukses atau error.
     */
    public static function removeFromCart($user, $cartId)
    {
        $cart = Cart::where('id', $cartId)
            ->where('user_id', $user->id)
            ->first();

        if (! $cart) {
            return ['error' => 'Item keranjang tidak ditemukan.'];
        }

        // Kembalikan status voucher (opsional tapi direkomendasikan)
        // Pastikan voucher masih ada sebelum diupdate statusnya
        if ($cart->voucher) {
            $cart->voucher->update(['status' => 'available']);
        }

        $cart->delete();

        return ['message' => 'Item keranjang berhasil dihapus.'];
    }
}
