<?php

namespace VistiqLearner\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use VistiqLearner\Http\Controllers\Assessment\ExamTakingAndSubmission\ExamController;
use VistiqLearner\Http\Controllers\Assessment\ExamTakingAndSubmission\ResultController;
use VistiqLearner\Http\Controllers\Authentication\AuthController;
use VistiqLearner\Http\Controllers\Knowledge\GrammarController;
use VistiqLearner\Http\Controllers\Knowledge\KnowledgeController;
use VistiqLearner\Http\Controllers\Practice\SkillPractice\SkillPracticeController;
use VistiqLearner\Http\Controllers\Profile\ProfileController;
use VistiqLearner\Http\Controllers\Search\LookupController;
use VistiqLearner\Http\Controllers\Site\HomeController;

/**
 * Compatibility dispatcher for the static demo's existing /demo/{screen} links.
 * Screen definitions and rendering stay inside their feature controllers.
 */
class DemoPageController extends Controller
{
    /** @var array<string, class-string<StaticPageController>> */
    private const SCREEN_CONTROLLERS = [
        'home' => HomeController::class,
        'welcome-demo' => AuthController::class,
        'login' => AuthController::class,
        'register' => AuthController::class,
        'forgot-password' => AuthController::class,
        'email-sent' => AuthController::class,
        'reset-password' => AuthController::class,
        'password-updated' => AuthController::class,
        'lookup' => LookupController::class,
        'lookup-overview' => LookupController::class,
        'lookup-collocations' => LookupController::class,
        'lookup-examples' => LookupController::class,
        'grammar' => GrammarController::class,
        'grammar-present-perfect' => GrammarController::class,
        'knowledge' => KnowledgeController::class,
        'knowledge-list' => KnowledgeController::class,
        'knowledge-review' => KnowledgeController::class,
        'saved-detail' => KnowledgeController::class,
        'ielts-skills' => SkillPracticeController::class,
        'skill-list' => SkillPracticeController::class,
        'skill-session' => SkillPracticeController::class,
        'ielts-matching' => SkillPracticeController::class,
        'ielts-writing' => SkillPracticeController::class,
        'ielts-speaking' => SkillPracticeController::class,
        'ielts-listening' => SkillPracticeController::class,
        'skill-result' => ResultController::class,
        'ielts-skill-result' => ResultController::class,
        'ielts-pending' => SkillPracticeController::class,
        'exam-config' => ExamController::class,
        'exams' => ExamController::class,
        'exam-detail' => ExamController::class,
        'exam-rules' => ExamController::class,
        'exam-room' => ExamController::class,
        'ielts-reading-room' => ExamController::class,
        'submit' => ExamController::class,
        'exam-result' => ResultController::class,
        'answer-review' => ExamController::class,
        'exam-leaderboard' => ExamController::class,
        'ielts-result' => ResultController::class,
        'ielts-exam-detail' => ExamController::class,
        'ielts-rules' => ExamController::class,
        'history' => ProfileController::class,
        'profile' => ProfileController::class,
    ];

    public function show(string $screen): View|RedirectResponse
    {
        if ($screen === 'skills') {
            return redirect()->route('demo.page', 'skill-list');
        }

        if ($screen === 'history') {
            return redirect()->route('profile.history');
        }
        if ($screen === 'exam-leaderboard' && request()->filled('exam')) {
            return redirect()->route('exams.leaderboard', request()->integer('exam'));
        }
        if (in_array($screen, ['exams', 'exam-config', 'exam-detail', 'exam-rules', 'exam-room', 'ielts-reading-room', 'submit', 'exam-result', 'answer-review', 'exam-leaderboard', 'ielts-result', 'ielts-exam-detail', 'ielts-rules'], true)) {
            return redirect()->route('exams.index');
        }
        $lookupTabs = [
            'lookup' => null,
            'lookup-overview' => null,
            'lookup-collocations' => 'collocations',
            'lookup-examples' => 'examples',
        ];

        if (array_key_exists($screen, $lookupTabs)) {
            $parameters = request()->only(['q', 'type', 'tab', 'page']);

            if ($lookupTabs[$screen] !== null) {
                $parameters['tab'] ??= $lookupTabs[$screen];
            }

            if ($screen !== 'lookup') {
                $parameters['q'] ??= 'achieve';
            }

            return redirect()->route('lookup.index', $parameters);
        }

        abort_unless(isset(self::SCREEN_CONTROLLERS[$screen]), 404);

        return app(self::SCREEN_CONTROLLERS[$screen])->show($screen);
    }
}
