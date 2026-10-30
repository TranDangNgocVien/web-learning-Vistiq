<?php

use Illuminate\Support\Facades\Route;
use VistiqLearner\Http\Controllers\DemoPageController;
use VistiqLearner\Http\Controllers\Site\HomeController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/demo/{screen}', [DemoPageController::class, 'show'])->name('demo.page');
