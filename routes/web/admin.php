<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function (): void {
    require __DIR__.'/admin/identity/authentication.php';

    Route::middleware(['auth', 'active.admin'])->group(function (): void {
        Route::middleware('admin')->group(function (): void {
            foreach ([
                'analytics',
                'search',
                'assessment/question-bank',
                'assessment/content-approval',
                'assessment/exams',
                'assessment/attempts',
                'learning/knowledge',
                'learning/skill-exercises',
                'identity/users-and-permissions',
                'system',
            ] as $moduleRoutes) {
                require __DIR__.'/admin/'.$moduleRoutes.'.php';
            }
        });
    });

    require __DIR__.'/admin/demo.php';
});
