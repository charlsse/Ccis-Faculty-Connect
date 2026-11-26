<?php

use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard/treasury')->name('wallet.')->middleware(['auth', 'account.usable', 'onboarding', 'two-factor.confirmed'])->group(function () {
    Route::get('/', [WalletController::class, 'index'])->name('index');
    Route::post('/targets', [WalletController::class, 'target'])->middleware('throttle:10,1')->name('target');
    Route::post('/review', [WalletController::class, 'review'])->middleware('throttle:10,1')->name('review');
    Route::post('/confirm', [WalletController::class, 'confirm'])->middleware('throttle:5,1')->name('confirm');
    Route::post('/withdrawals/{withdrawal}/simulate', [WalletController::class, 'simulate'])->middleware('throttle:20,1')->name('simulate');
    Route::post('/settlements/{settlement}/simulate', [WalletController::class, 'settle'])->middleware('throttle:20,1')->name('settle');
});
