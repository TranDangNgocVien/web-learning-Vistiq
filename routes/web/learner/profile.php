<?php

use Illuminate\Support\Facades\Route;
use VistiqLearner\Http\Controllers\Profile\ProfileController;

Route::middleware(['auth', 'active.learner'])->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'index'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->middleware('throttle:20,1')->name('profile.update');
    Route::get('/profile/history', [ProfileController::class, 'history'])->name('profile.history');
    Route::get('/profile/saved', [ProfileController::class, 'saved'])->name('profile.saved');
});
