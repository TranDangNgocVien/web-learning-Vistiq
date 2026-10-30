<?php

use Illuminate\Support\Facades\Route;
use VistiqLearner\Http\Controllers\Search\DictionaryApiController;

Route::prefix('v1/dictionary')->middleware('throttle:60,1')->name('api.dictionary.')->group(function (): void {
    Route::get('/', [DictionaryApiController::class, 'index'])->name('index');
    Route::get('/suggestions', [DictionaryApiController::class, 'suggestions'])->name('suggestions');
    Route::get('/entries/{slug}', [DictionaryApiController::class, 'show'])->name('show');
});
