<?php

namespace VistiqLearner\Http\Controllers\Assessment\ExamTakingAndSubmission;

use Illuminate\View\View;
use VistiqLearner\Http\Controllers\StaticPageController;

class ResultController extends StaticPageController
{
    private const SCREENS = [
        'exam-result' => ['title' => 'Kết quả bài làm', 'frame' => 'U19', 'view' => 'pages.learner.assessment.exam-taking-and-submission.results.show', 'section' => 'exams', 'description' => 'Xem điểm số và phân tích theo từng Part.', 'variant' => 'toeic'],
        'ielts-result' => ['title' => 'Kết quả IELTS Reading', 'frame' => 'U22', 'view' => 'pages.learner.assessment.exam-taking-and-submission.results.show', 'section' => 'exams', 'description' => 'Đối chiếu số câu đúng với band điểm ước tính.', 'variant' => 'ielts'],
        'skill-result' => ['title' => 'Kết quả luyện tập', 'frame' => 'U26', 'view' => 'pages.learner.assessment.exam-taking-and-submission.results.show', 'section' => 'skills', 'description' => 'Phân tích câu đúng, kiến thức cần củng cố và gợi ý tiếp theo.', 'variant' => 'skill'],
        'ielts-skill-result' => ['title' => 'Kết quả luyện IELTS', 'frame' => 'U30', 'view' => 'pages.learner.assessment.exam-taking-and-submission.results.show', 'section' => 'skills', 'description' => 'Band điểm ước tính và phân tích kỹ năng.', 'variant' => 'ielts'],
    ];

    public function show(string $screen): View
    {
        return $this->renderKnownScreen($screen, self::SCREENS);
    }
}
