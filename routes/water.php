<?php

use App\Http\Controllers\WaterCollectionController as Collections;
use App\Http\Controllers\WaterPaymentController as Payments;
use App\Http\Middleware\WaterFailureResponse;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/payments/xendit-demo', [\App\Http\Controllers\XenditDemoController::class, 'webhook'])->middleware('throttle:120,1,water.demo.webhook:');
Route::get('/payments/xendit/demo-return/{reference}', [\App\Http\Controllers\XenditDemoController::class, 'returnToMain'])->whereUuid('reference');

Route::get('/payments/xendit/return', fn () => response(
    'Checkout has ended. Return to FacultyConnect locally and check your contribution status. This page does not confirm payment.',
    200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']
))->name('water.gateway.xendit.return');

Route::post('/webhooks/payments/xendit', [\App\Http\Controllers\WaterGatewayController::class, 'webhook'])
    ->middleware('throttle:120,1,water.water.gateway.xendit:')->name('water.gateway.xendit');

Route::post('/webhooks/payments/paymongo', [\App\Http\Controllers\WaterGatewayController::class, 'webhook'])
    ->middleware('throttle:120,1,water.water.gateway.webhook:')->name('water.gateway.webhook');

Route::prefix('dashboard/water')->name('water.')->middleware(['auth', 'account.usable', 'onboarding', 'two-factor.confirmed', WaterFailureResponse::class])->group(function () {
    Route::get('/demo', [\App\Http\Controllers\XenditDemoController::class, 'index'])->name('demo.index');
    Route::post('/members/{member}/demo', [\App\Http\Controllers\XenditDemoController::class, 'start'])->middleware('throttle:5,1,water.demo.start:')->name('demo.start');
    Route::get('/demo/{attempt}', [\App\Http\Controllers\XenditDemoController::class, 'show'])->name('demo.show');
    Route::get('/demo/{attempt}/status', [\App\Http\Controllers\XenditDemoController::class, 'status'])->middleware('throttle:30,1,water.demo.status:')->name('demo.status');
    Route::post('/demo/{attempt}/check', [\App\Http\Controllers\XenditDemoController::class, 'check'])->middleware('throttle:4,1,water.demo.check:')->name('demo.check');
    Route::get('/demo/{attempt}/receipt', [\App\Http\Controllers\XenditDemoController::class, 'receipt'])->name('demo.receipt');
    Route::get('/', [Collections::class, 'index'])->name('index');
    Route::post('/notices/{notice}/read', [Collections::class, 'readNotice'])->name('notices.read');
    Route::middleware('permission:payments.view')->group(function () {
        Route::get('/reports', [Collections::class, 'reports'])->name('reports');
        Route::get('/audit', [Collections::class, 'audit'])->name('audit');
        Route::get('/{collection}/report', [Payments::class, 'report'])->name('report');
    });
    Route::middleware('permission:payments.manage')->group(function () {
        Route::get('/create', [Collections::class, 'create'])->name('create');
        Route::post('/', [Collections::class, 'store'])->middleware('throttle:20,1,water.store:')->name('store');
        Route::get('/accounts', [Collections::class, 'accounts'])->name('accounts');
        Route::post('/accounts', [Collections::class, 'storeAccount'])->middleware('throttle:10,1,water.accounts.store:')->name('accounts.store');
        Route::patch('/accounts/{account}', [Collections::class, 'activateAccount'])->name('accounts.activate');
        Route::get('/accounts/{account}/qr', [Collections::class, 'accountQr'])->name('accounts.qr');
        Route::get('/queue', [Payments::class, 'queue'])->name('queue');
        Route::get('/{collection}/edit', [Collections::class, 'edit'])->name('edit');
        Route::put('/{collection}', [Collections::class, 'update'])->name('update');
        Route::patch('/{collection}/status', [Collections::class, 'transition'])->name('transition');
        Route::post('/submissions/{submission}/review', [Payments::class, 'review'])->middleware('throttle:30,1,water.review:')->name('review');
        Route::get('/members/{member}/cash', [Payments::class, 'cashForm'])->name('cash');
        Route::post('/members/{member}/cash', [Payments::class, 'cash'])->middleware('throttle:20,1,water.cash.store:')->name('cash.store');
        Route::post('/members/{member}/waive', [Payments::class, 'waive'])->middleware('throttle:20,1,water.waive:')->name('waive');
    });
    Route::get('/submissions/{submission}', [Payments::class, 'show'])->name('submission');
    Route::get('/members/{member}/pay', [Payments::class, 'checkout'])->name('checkout');
    Route::post('/members/{member}/online', [\App\Http\Controllers\WaterGatewayController::class, 'start'])->middleware('throttle:5,1,water.online.start:')->name('online.start');
    Route::get('/online/{attempt}', [\App\Http\Controllers\WaterGatewayController::class, 'show'])->name('online.show');
    Route::get('/online/{attempt}/status', [\App\Http\Controllers\WaterGatewayController::class, 'status'])->middleware('throttle:20,1,water.online.status:')->name('online.status');
    Route::post('/online/{attempt}/check', [\App\Http\Controllers\WaterGatewayController::class, 'check'])->middleware('throttle:4,1,water.online.check:')->name('online.check');
    Route::get('/submissions/{submission}/proof', [Payments::class, 'proof'])->name('proof');
    Route::post('/members/{member}/submit', [Payments::class, 'submit'])->middleware('throttle:5,1,water.submit:')->name('submit');
    Route::get('/receipts/{receipt}', [Payments::class, 'receipt'])->name('receipt');
    Route::get('/{collection}/qr', [Collections::class, 'qr'])->name('qr');
    Route::get('/{collection}', [Collections::class, 'show'])->name('show');
});
