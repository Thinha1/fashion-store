<?php

use App\Http\Controllers\Storefront\CollectionController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductAssistController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('san-pham', [ProductController::class, 'index'])->name('products.index');
// Customer-facing shopping-assist chat — guest-callable on purpose (see
// ProductAssistController), never gated behind auth/permission like the
// admin product-authoring assistant is.
Route::post('san-pham/goi-y-ai', ProductAssistController::class)
    ->middleware('throttle:shopping-assist')->name('products.assist');
Route::get('san-pham/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('bo-suu-tap', [CollectionController::class, 'index'])->name('collections.index');
Route::get('bo-suu-tap/{brand:slug}', [CollectionController::class, 'show'])->name('collections.show');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('tai-khoan', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('tai-khoan', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
