<?php

use Illuminate\Support\Facades\Route;
use VistiqLearner\Http\Controllers\Search\LookupController;

Route::get('/lookup', [LookupController::class, 'index'])->middleware('throttle:60,1')->name('lookup.index');
Route::get('/lookup/{slug}', [LookupController::class, 'entry'])->middleware('throttle:60,1')->name('lookup.entry');
