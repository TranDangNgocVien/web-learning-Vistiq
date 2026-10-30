<?php

namespace VistiqLearner\Http\Controllers\Practice\SkillPractice;

use Illuminate\View\View;
use VistiqLearner\Http\Controllers\StaticPageController;

class SkillPracticeController extends StaticPageController
{
    private const SCREENS = [
        'ielts-skills' => ['title' => 'Chọn kỹ năng IELTS', 'frame' => 'U25', 'view' => 'pages.learner.practice.skill-practice.skills.ielts', 'section' => 'skills', 'description' => 'Luyện riêng Listening, Reading, Writing hoặc Speaking.', 'show_heading' => false],
        'skill-list' => ['title' => 'Bài luyện theo kỹ năng', 'frame' => 'U34', 'view' => 'pages.learner.practice.skill-practice.skills.catalog', 'section' => 'skills', 'description' => 'Chọn một bài ngắn, tập trung vào đúng dạng cần luyện.', 'show_heading' => false],
        'skill-session' => ['title' => 'TOEIC Part 5 – Incomplete Sentences', 'frame' => 'U09', 'view' => 'pages.learner.practice.skill-practice.skills.runner', 'section' => 'skills', 'description' => 'Luyện từng câu và xem giải thích ngay sau khi trả lời.', 'variant' => 'toeic', 'show_heading' => false],
        'ielts-matching' => ['title' => 'IELTS Reading – Matching Headings', 'frame' => 'U10', 'view' => 'pages.learner.practice.skill-practice.skills.runner', 'section' => 'skills', 'description' => 'Nối tiêu đề phù hợp với ý chính của từng đoạn.', 'variant' => 'matching', 'show_heading' => false],
        'ielts-writing' => ['title' => 'IELTS Writing – Task 2', 'frame' => 'U11', 'view' => 'pages.learner.practice.skill-practice.skills.runner', 'section' => 'skills', 'description' => 'Lập luận rõ ràng và phát triển ý cho bài viết học thuật.', 'variant' => 'writing', 'show_heading' => false],
        'ielts-speaking' => ['title' => 'IELTS Speaking – Part 2', 'frame' => 'U12', 'view' => 'pages.learner.practice.skill-practice.skills.runner', 'section' => 'skills', 'description' => 'Chuẩn bị ý và ghi âm phần trả lời theo cue card.', 'variant' => 'speaking', 'show_heading' => false],
        'ielts-listening' => ['title' => 'IELTS Listening – Form Completion', 'frame' => 'U28', 'view' => 'pages.learner.practice.skill-practice.skills.runner', 'section' => 'skills', 'description' => 'Nghe thông tin và hoàn thành biểu mẫu.', 'variant' => 'listening', 'show_heading' => false],
        'ielts-pending' => ['title' => 'Bài IELTS chờ đánh giá', 'frame' => 'U31', 'view' => 'pages.learner.assessment.exam-taking-and-submission.results.pending', 'section' => 'skills', 'description' => 'Bài tự luận đang chờ chấm theo tiêu chí IELTS.'],
    ];

    public function show(string $screen): View
    {
        return $this->renderKnownScreen($screen, self::SCREENS);
    }
}
