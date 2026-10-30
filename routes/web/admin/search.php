<?php

use Illuminate\Support\Facades\Route;
use VistiqAdmin\Http\Controllers\Admin\Search\AdminSearchController;

Route::get('/search', [AdminSearchController::class, 'index'])->middleware('can:admin.access')->name('search');
