<?php

use Illuminate\Support\Facades\Route;
use VistiqLearner\Http\Controllers\Assessment\ExamTakingAndSubmission\AttemptHistoryController;
use VistiqLearner\Http\Controllers\Assessment\ExamTakingAndSubmission\AttemptRunnerController;
use VistiqLearner\Http\Controllers\Assessment\ExamTakingAndSubmission\ExamLeaderboardController;

Route::middleware(['auth', 'active.learner'])->group(function (): void {
    Route::get('/exams', [AttemptRunnerController::class, 'index'])->name('exams.index');
    Route::get('/exams/{exam}/leaderboard', [ExamLeaderboardController::class, 'index'])->name('exams.leaderboard');
    Route::get('/attempts/history', [AttemptHistoryController::class, 'index'])->name('attempts.history');
    Route::post('/exams/{exam}/start', [AttemptRunnerController::class, 'start'])->middleware('throttle:10,1')->name('exams.start');
    Route::get('/attempts/{attempt}', [AttemptRunnerController::class, 'show'])->name('attempts.show');
    Route::get('/attempts/{attempt}/media/{asset}', [AttemptRunnerController::class, 'media'])->name('attempts.media');
    Route::get('/attempts/{attempt}/state', [AttemptRunnerController::class, 'state'])->name('attempts.state');
    Route::get('/attempts/{attempt}/result', [AttemptRunnerController::class, 'result'])->name('attempts.result');

    foreach (['answers', 'events', 'pause', 'resume', 'section', 'navigate', 'submit', 'recording', 'audio'] as $action) {
        Route::post('/attempts/{attempt}/'.$action, [AttemptRunnerController::class, $action])
            ->middleware('throttle:120,1')
            ->name('attempts.'.$action);
    }
});
