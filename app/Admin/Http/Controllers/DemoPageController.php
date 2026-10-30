<?php

namespace VistiqAdmin\Http\Controllers;

use Illuminate\View\View;
use VistiqAdmin\Http\Controllers\Admin\Assessment\Attempts\AttemptController;
use VistiqAdmin\Http\Controllers\Admin\Analytics\DashboardController;
use VistiqAdmin\Http\Controllers\Admin\Assessment\Exams\ExamBuilderController;
use VistiqAdmin\Http\Controllers\Admin\Assessment\Exams\ExamRuleController;
use VistiqAdmin\Http\Controllers\Admin\Assessment\Attempts\GradingController;
use VistiqAdmin\Http\Controllers\Admin\Learning\Knowledge\GrammarController as AdminGrammarController;
use VistiqAdmin\Http\Controllers\Admin\Analytics\LeaderboardController as AdminLeaderboardController;
use VistiqAdmin\Http\Controllers\Admin\Assessment\QuestionBank\QuestionController;
use VistiqAdmin\Http\Controllers\Admin\Identity\Authentication\AuthController;

/**
 * Dispatch the admin prototype screens to their feature controllers.
 */
class DemoPageController extends Controller
{
    /** @var array<string, class-string<StaticPageController>> */
    private const SCREEN_CONTROLLERS = [
        'admin' => DashboardController::class,
        'admin-login' => AuthController::class,
        'admin-verify' => AuthController::class,
        'admin-questions' => QuestionController::class,
        'admin-question-edit' => QuestionController::class,
        'admin-exam-builder' => ExamBuilderController::class,
        'admin-rules' => ExamRuleController::class,
        'admin-ielts-builder' => ExamBuilderController::class,
        'admin-publish' => ExamBuilderController::class,
        'admin-attempts' => AttemptController::class,
        'admin-grading' => GradingController::class,
        'admin-grammar' => AdminGrammarController::class,
        'admin-leaderboard' => AdminLeaderboardController::class,
        'admin-incident' => AttemptController::class,
    ];

    public function show(string $screen): View
    {
        abort_unless(isset(self::SCREEN_CONTROLLERS[$screen]), 404);

        return app(self::SCREEN_CONTROLLERS[$screen])->show($screen);
    }
}
