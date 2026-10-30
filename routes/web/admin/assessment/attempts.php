<?php

use Illuminate\Support\Facades\Route;
use VistiqAdmin\Http\Controllers\Admin\Assessment\Attempts\AttemptController;
use VistiqAdmin\Http\Controllers\Admin\Assessment\Attempts\GradingController;

Route::get('/attempts', [AttemptController::class, 'index'])->middleware('can:results.view')->name('attempts.index');
Route::get('/attempts/{attempt}', [AttemptController::class, 'detail'])->middleware('can:results.view')->name('attempts.show');
Route::get('/attempts/{attempt}/recordings/{asset}', [AttemptController::class, 'recording'])->name('attempts.recording');
Route::get('/grading', [GradingController::class, 'index'])->middleware('can:results.grade')->name('grading.index');
Route::post('/grading/{answer}', [GradingController::class, 'store'])->middleware('can:results.grade')->name('grading.store');
Route::post('/grading/{answer}/ai', [GradingController::class, 'ai'])->middleware(['can:results.grade', 'throttle:6,1'])->name('grading.ai');
Route::post('/attempts/{attempt}/moderate', [AttemptController::class, 'moderate'])->middleware('can:reports.review')->name('attempts.moderate');
