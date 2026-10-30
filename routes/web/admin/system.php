<?php

use Illuminate\Support\Facades\Route;
use VistiqAdmin\Http\Controllers\Admin\System\DatabaseBackupController;
use VistiqAdmin\Http\Controllers\Admin\System\SettingsController;

Route::get('/settings', [SettingsController::class, 'index'])->middleware('can:settings.manage')->name('settings.index');
Route::put('/settings/{group}', [SettingsController::class, 'update'])->middleware(['can:settings.manage', 'throttle:20,1'])->name('settings.update');
Route::get('/settings/database', [DatabaseBackupController::class, 'show'])->middleware('can:settings.manage')->name('settings.database');
Route::post('/settings/database/backup', [DatabaseBackupController::class, 'download'])->middleware(['can:settings.manage', 'throttle:3,1'])->name('settings.database.backup');
Route::post('/settings/database/restore', [DatabaseBackupController::class, 'restore'])->middleware(['can:settings.manage', 'throttle:1,1'])->name('settings.database.restore');
Route::get('/settings/jobs', [SettingsController::class, 'jobs'])->middleware('can:settings.manage')->name('settings.jobs');
Route::get('/audit', [SettingsController::class, 'audit'])->middleware('can:audit.view')->name('audit.index');
Route::post('/ai', [SettingsController::class, 'ai'])->middleware(['can:content.edit', 'throttle:6,1'])->name('ai.create');
Route::get('/ai/{task}', [SettingsController::class, 'aiStatus'])->name('ai.show');

Route::post('/settings/database/restore/preflight', [DatabaseBackupController::class, 'preflight'])->middleware(['can:settings.manage', 'throttle:3,1'])->name('settings.database.preflight');
Route::post('/settings/database/backups/{artifact}/download', [DatabaseBackupController::class, 'downloadArtifact'])->middleware(['can:settings.manage', 'throttle:6,1'])->name('settings.database.artifact');
Route::get('/settings/database/operations/{operation}', [DatabaseBackupController::class, 'status'])->middleware('can:settings.manage')->name('settings.database.status');
