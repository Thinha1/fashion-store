<?php

use App\Http\Controllers\Storefront\AddressController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\CollectionController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\PaymentProofController;
use App\Http\Controllers\Storefront\ProductAssistController;
use App\Http\Controllers\Storefront\ProductAssistStreamController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ProfileController;
use App\Http\Controllers\Webhooks\SepayWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('san-pham', [ProductController::class, 'index'])->name('products.index');
// Customer-facing shopping-assist chat — guest-callable on purpose (see
// ProductAssistController), never gated behind auth/permission like the
// admin product-authoring assistant is.
Route::post('san-pham/goi-y-ai', ProductAssistController::class)
    ->middleware('throttle:shopping-assist')->name('products.assist');
// SSE variant of the same call — same rate limiter, kept as a separate
// route+controller so the JSON one above (and its tests) never has to
// change shape; see ShoppingAssistResolver for the shared logic.
Route::post('san-pham/goi-y-ai/stream', ProductAssistStreamController::class)
    ->middleware('throttle:shopping-assist')->name('products.assist-stream');
Route::get('san-pham/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('bo-suu-tap', [CollectionController::class, 'index'])->name('collections.index');
Route::get('bo-suu-tap/{brand:slug}', [CollectionController::class, 'show'])->name('collections.show');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('tai-khoan', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('tai-khoan', [ProfileController::class, 'update'])->name('profile.update');

    Route::prefix('tai-khoan/dia-chi')->name('addresses.')->controller(AddressController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('tao-moi', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{address}/sua', 'edit')->name('edit');
        Route::put('{address}', 'update')->name('update');
        Route::patch('{address}/mac-dinh', 'makeDefault')->name('default');
        Route::delete('{address}', 'destroy')->name('destroy');
    });

    Route::prefix('gio-hang')->name('cart.')->controller(CartController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::patch('{cartItem}', 'update')->name('update');
        Route::delete('{cartItem}', 'destroy')->name('destroy');
    });

    Route::get('thanh-toan', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('thanh-toan', [CheckoutController::class, 'store'])
        ->middleware('throttle:10,1')->name('checkout.store');

    Route::get('don-hang', [OrderController::class, 'index'])->name('orders.index');
    Route::get('don-hang/{order:order_number}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('don-hang/{order:order_number}/trang-thai-thanh-toan', [OrderController::class, 'paymentStatus'])
        ->middleware('throttle:30,1')->name('orders.payment-status');
    Route::post('don-hang/{order:order_number}/chung-tu-thanh-toan', [PaymentProofController::class, 'store'])
        ->middleware('throttle:6,1')->name('orders.payment-proof');
    Route::patch('don-hang/{order:order_number}/huy', [OrderController::class, 'cancel'])->name('orders.cancel');
});

// SePay → shop: no session/CSRF (exempted in bootstrap/app.php); the
// controller checks the "Authorization: Apikey ..." header itself.
Route::post('webhooks/sepay', SepayWebhookController::class)
    ->middleware('throttle:60,1')->name('webhooks.sepay');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
