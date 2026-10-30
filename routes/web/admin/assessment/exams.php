<?php

use Illuminate\Support\Facades\Route;
use VistiqAdmin\Http\Controllers\Admin\Assessment\Exams\ExamBuilderController;

Route::post('/exams/generate', [ExamBuilderController::class, 'generate'])->middleware('can:content.edit')->name('exams.generate');
Route::post('/exams/preview-draft', [ExamBuilderController::class, 'previewDraft'])->middleware('can:content.edit')->name('exams.preview-draft');
Route::get('/exams/deleted', [ExamBuilderController::class, 'deleted'])->middleware('can:content.view')->name('exams.deleted');
Route::post('/exams/deleted/{exam}/restore', [ExamBuilderController::class, 'restore'])->middleware('can:content.edit')->whereNumber('exam')->name('exams.restore');
Route::delete('/exams/{exam}', [ExamBuilderController::class, 'destroy'])->middleware('can:content.edit')->name('exams.destroy');
Route::resource('/exams', ExamBuilderController::class)->except(['show', 'destroy'])->middleware('can:content.view');
Route::get('/exams/{exam}/preview', [ExamBuilderController::class, 'preview'])->middleware('can:content.view')->name('exams.preview');
Route::get('/exams/{exam}/validation', [ExamBuilderController::class, 'validation'])->middleware('can:content.view')->name('exams.validation');
