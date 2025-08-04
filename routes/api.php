<?php

use App\Admin\Category\Controllers\CategoryController;
use App\Admin\Voucher\Controllers\VoucherController;
use App\Auth\Controllers\AuthController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\UserController;
use App\User\Balance\Controllers\BalanceController;
use App\User\Profile\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum', 'isAdmin')->group(function () {

    Route::prefix('admin')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index']);
    });

    Route::prefix('admin/users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
    });

    Route::prefix('admin/categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{id}', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'destroy']);
    });

    Route::prefix('admin/vouchers')->group(function () {
        Route::get('/', [VoucherController::class, 'index']);
        Route::post('/', [VoucherController::class, 'store']);
        Route::put('/{id}', [VoucherController::class, 'update']);
        Route::delete('/{id}', [VoucherController::class, 'destroy']);
    });

    Route::prefix('admin/transactions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AdminHistoryTransactionController::class, 'index']);
        Route::get('/pending', [\App\Http\Controllers\Admin\AdminHistoryTransactionController::class, 'pendingHistory']);
    });

});

Route::prefix('admin/topups')->middleware(['auth:sanctum', 'isAdmin'])->group(function () {
    Route::get('/', [\App\Admin\Topup\Controllers\TopupController::class, 'index']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('balance/topup', [BalanceController::class, 'topup']);
    Route::post('balance/topup-snap', [BalanceController::class, 'topup']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/balance', [\App\User\Balance\Controllers\BalanceController::class, 'getBalance']);
    Route::get('/user/topup/history', [\App\User\Balance\Controllers\BalanceController::class, 'history']);

});

Route::prefix('user/purchase')->middleware('auth:sanctum')->group(function () {
    Route::post('/cart', [\App\User\Purchase\Controllers\PurchaseController::class, 'addToCart']);
    Route::get('/cart', [\App\User\Purchase\Controllers\PurchaseController::class, 'viewCart']);
    Route::post('/cart/remove', [\App\User\Purchase\Controllers\PurchaseController::class, 'removeFromCart']);
    Route::post('/checkout', [\App\User\Purchase\Controllers\PurchaseController::class, 'checkout']);
    Route::get('/history', [\App\User\Purchase\Controllers\PurchaseController::class, 'history']);
    Route::get('/pending', [\App\User\Purchase\Controllers\PurchaseController::class, 'pendingTransactions']); // 👈 ini
});

Route::prefix('user/purchase')->middleware('auth:sanctum')->group(function () {
    Route::post('/checkout-midtrans', [\App\User\Purchase\Controllers\PurchaseController::class, 'checkoutWithMidtrans']);
    Route::post('/checkout-midtrans-qris', [\App\User\Purchase\Controllers\PurchaseController::class, 'checkoutWithMidtransQRIS']);
});

Route::post('/midtrans/callback', [\App\Http\Controllers\MidtransCallbackController::class, 'handle']);

Route::get('/vouchers', [App\Http\Controllers\Api\VoucherController::class, 'index']);
// Route::get('/vouchers/{id}/related', [App\Http\Controllers\Api\VoucherController::class, 'related']);

Route::middleware('auth:sanctum')->prefix('user')->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
});
