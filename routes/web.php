<?php

use App\Http\Controllers\PayPalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/checkout', [PayPalController::class, 'checkout'])->name('checkout');
Route::get('/payment/execute', [PayPalController::class, 'executeAgreement'])->name('execute-agreement');
Route::get('/payment/status', [PayPalController::class, 'paymentStatus'])->name('payment-status');



