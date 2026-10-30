<?php

use Illuminate\Support\Facades\Route;
use VistiqAdmin\Http\Controllers\Admin\Analytics\DashboardController;
use VistiqAdmin\Http\Controllers\Admin\Analytics\LeaderboardController;

Route::get('/', [DashboardController::class, 'index'])->middleware('can:admin.access')->name('dashboard');

Route::middleware('can:results.view')->group(function (): void {
    Route::get('/analytics', [DashboardController::class, 'analytics'])->name('analytics');
    Route::get('/analytics/export', [DashboardController::class, 'export'])->middleware('can:results.export')->name('analytics.export');

    Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');
    Route::get('/leaderboard/export', [LeaderboardController::class, 'export'])->middleware('can:results.export')->name('leaderboard.export');
});

Route::post('/leaderboard/{exam}/reset', [LeaderboardController::class, 'reset'])->middleware('can:content.publish')->name('leaderboard.reset');
