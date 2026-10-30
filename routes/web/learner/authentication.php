<?php

use Illuminate\Support\Facades\Route;
use VistiqLearner\Http\Controllers\Authentication\AccountPasswordController;
use VistiqLearner\Http\Controllers\Authentication\AuthController;

Route::middleware('guest.learner')->group(function (): void {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:6,1')->name('login.store');

    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1')->name('register.store');

    Route::get('/forgot-password', [AuthController::class, 'forgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/forgot-password/sent', [AuthController::class, 'show'])->defaults('screen', 'email-sent')->name('password.sent');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active.learner'])->group(function (): void {
    Route::get('/account/password', [AccountPasswordController::class, 'edit'])->name('account.password');
    Route::put('/account/password', [AccountPasswordController::class, 'update'])->middleware('throttle:5,1')->name('account.password.update');
});
