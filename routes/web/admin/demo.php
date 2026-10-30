<?php

use Illuminate\Support\Facades\Route;

Route::get('/demo/{screen}', function (string $screen) {
$routes = [
'admin' => 'admin.dashboard',
'admin-login' => 'admin.login',
'admin-verify' => 'admin.otp.show',
'admin-questions' => 'admin.questions.index',
'admin-question-edit' => 'admin.questions.create',
'admin-reading-bank' => 'admin.readings.index',
'admin-audio-bank' => 'admin.audio.index',
'admin-exam-builder' => 'admin.exams.create',
'admin-ielts-builder' => 'admin.exams.create',
'admin-rules' => 'admin.exams.index',
'admin-publish' => 'admin.exams.index',
'admin-attempts' => 'admin.attempts.index',
'admin-incident' => 'admin.attempts.index',
'admin-grading' => 'admin.grading.index',
'admin-grammar' => 'admin.knowledge.index',
'admin-leaderboard' => 'admin.leaderboard.index',
'admin-settings' => 'admin.settings.index',
];
abort_unless(isset($routes[$screen]), 404);

return redirect()->route($routes[$screen]);
})->name('demo.page');
