<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AuthController;

Route::middleware('auth:sanctum')->group(function () {
    Route::resource('products', ProductController::class);
    Route::resource('customers', CustomerController::class);
    Route::resource('vendors', VendorController::class);
    Route::resource('carts', CartController::class);
    Route::post('carts/{cart}/checkout', [CartController::class, 'checkout']);
    Route::resource('cart-items', CartItemController::class);
    Route::resource('payments', PaymentController::class);
    Route::post('payments/mpesa', [PaymentController::class, 'initiateMpesa']);
});

Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/register', [AuthController::class, 'register']);
?>
