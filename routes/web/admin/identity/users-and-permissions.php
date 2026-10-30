<?php

use Illuminate\Support\Facades\Route;
use VistiqAdmin\Http\Controllers\Admin\Identity\UsersAndPermissions\UserManagementController;

Route::get('/users', [UserManagementController::class, 'index'])->middleware('can:users.manage')->name('users.index');
Route::get('/users/{user}', [UserManagementController::class, 'show'])->middleware('can:users.manage')->name('users.show');
Route::post('/users/{user}/{action}', [UserManagementController::class, 'action'])->middleware(['can:users.manage', 'throttle:20,1'])->name('users.action');

Route::get('/roles', [UserManagementController::class, 'roles'])->middleware('can:roles.manage')->name('roles.index');
Route::post('/roles', [UserManagementController::class, 'storeRole'])->middleware('can:roles.manage')->name('roles.store');
Route::post('/roles/{role}', [UserManagementController::class, 'updateRole'])->middleware('can:roles.manage')->name('roles.update');
Route::delete('/roles/{role}', [UserManagementController::class, 'destroyRole'])->middleware('can:roles.manage')->name('roles.destroy');
