<?php

use Illuminate\Support\Facades\Route;
use VistiqLearner\Http\Controllers\Knowledge\GrammarController;
use VistiqLearner\Http\Controllers\Knowledge\KnowledgeController;

Route::get('/grammar', [GrammarController::class, 'index'])->name('grammar.index');
Route::get('/grammar/{slug}', [GrammarController::class, 'detail'])->name('grammar.show');

Route::middleware(['auth', 'active.learner'])->group(function (): void {
    Route::get('/knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
    Route::get('/knowledge/saved', [KnowledgeController::class, 'savedList'])->name('knowledge.saved');
    Route::post('/knowledge/collections', [KnowledgeController::class, 'storeCollection'])->middleware('throttle:30,1')->name('knowledge.collections.store');
    Route::post('/knowledge/{slug}/save', [KnowledgeController::class, 'save'])->middleware('throttle:30,1')->name('knowledge.save');
    Route::delete('/knowledge/{slug}/save', [KnowledgeController::class, 'unsave'])->middleware('throttle:30,1')->name('knowledge.unsave');
});
Route::get('/knowledge/{slug}', [KnowledgeController::class, 'detail'])->name('knowledge.show');
