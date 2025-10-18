<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Controllers\TransactionController;
use App\Models\Wallet;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/transactions', [TransactionController::class, 'allTransactions']);
Route::post('/transactions', [TransactionController::class, 'storeTransaction'])->middleware(EnsureIdempotency::class);
Route::get('/wallets/{wallet}', function(Wallet $wallet){
    return response()->json([
        "currency" => $wallet->currency,
        "balance" => number_format($wallet->balance, 2, '.', ',')
    ]);
});