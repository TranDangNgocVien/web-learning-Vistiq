<?php

use Illuminate\Support\Facades\Route;
use VistiqAdmin\Http\Controllers\Admin\Identity\Authentication\AdminOtpController;
use VistiqAdmin\Http\Controllers\Admin\Identity\Authentication\AuthController;

Route::middleware('guest.admin')->group(function (): void {
    Route::get('/forgot-password', [AuthController::class, 'passwordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'passwordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('password.update');
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:6,1')->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active.admin'])->group(function (): void {
    Route::get('/verify', [AdminOtpController::class, 'show'])->name('otp.show');
    Route::post('/verify', [AdminOtpController::class, 'verify'])->middleware('throttle:5,1')->name('otp.verify');
    Route::post('/verify/resend', [AdminOtpController::class, 'send'])->middleware('throttle:1,1')->name('otp.send');
});