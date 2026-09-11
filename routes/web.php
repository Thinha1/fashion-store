<?php

use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('tai-khoan', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('tai-khoan', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
