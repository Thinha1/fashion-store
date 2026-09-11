<?php

use App\Http\Controllers\Storefront\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Storefront\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Storefront\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Storefront\Auth\NewPasswordController;
use App\Http\Controllers\Storefront\Auth\PasswordResetLinkController;
use App\Http\Controllers\Storefront\Auth\RegisteredUserController;
use App\Http\Controllers\Storefront\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('dang-ky', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('dang-ky', [RegisteredUserController::class, 'store']);

    Route::get('dang-nhap', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('dang-nhap', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    Route::get('quen-mat-khau', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('quen-mat-khau', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('dat-lai-mat-khau/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('dat-lai-mat-khau', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('xac-minh-email', EmailVerificationPromptController::class)->name('verification.notice');

    Route::get('xac-minh-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('xac-minh-email/gui-lai', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::post('dang-xuat', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
