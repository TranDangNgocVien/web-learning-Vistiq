<?php

namespace VistiqLearner\Http\Controllers\Assessment\ExamTakingAndSubmission;

use Illuminate\View\View;
use VistiqLearner\Http\Controllers\StaticPageController;

class ExamController extends StaticPageController
{
    private const SCREENS = [
        'exam-config' => ['title' => 'Chọn chế độ luyện tập', 'frame' => 'U08', 'view' => 'pages.learner.assessment.exam-taking-and-submission.exams.config', 'section' => 'exams', 'description' => 'Điều chỉnh số câu, độ khó và thời lượng theo mục tiêu.', 'show_heading' => false],
        'exams' => ['title' => 'Luyện đề TOEIC / IELTS', 'frame' => 'U13', 'view' => 'pages.learner.assessment.exam-taking-and-submission.exams.index', 'section' => 'exams', 'description' => 'Chọn đề thi chuẩn format hoặc đề do cộng đồng tạo.', 'show_heading' => false],
        'exam-detail' => ['title' => 'TOEIC Full Test 01', 'frame' => 'U14', 'view' => 'pages.learner.assessment.exam-taking-and-submission.exams.show', 'section' => 'exams', 'description' => 'Thông tin đề thi và cấu trúc từng phần.', 'show_heading' => false],
        'exam-rules' => ['title' => 'Quy định trước thi', 'frame' => 'U15', 'view' => 'pages.learner.assessment.exam-taking-and-submission.exams.rules', 'section' => 'exams', 'description' => 'Nắm các lưu ý trước khi vào phòng thi có tính giờ.'],
        'exam-room' => ['title' => 'TOEIC Full Test 01', 'frame' => 'U16', 'view' => 'pages.learner.assessment.exam-taking-and-submission.exams.runner', 'section' => 'exams', 'description' => 'Bài thi mô phỏng với đồng hồ và danh sách câu hỏi.', 'variant' => 'toeic-full', 'show_heading' => false],
        'ielts-reading-room' => ['title' => 'IELTS Reading – Test 01', 'frame' => 'U17', 'view' => 'pages.learner.assessment.exam-taking-and-submission.exams.runner', 'section' => 'exams', 'description' => 'Đọc bài văn và trả lời câu hỏi theo cấu trúc IELTS.', 'variant' => 'ielts-reading', 'show_heading' => false],
        'submit' => ['title' => 'Đã nộp bài', 'frame' => 'U18', 'view' => 'pages.learner.assessment.exam-taking-and-submission.exams.submit', 'section' => 'exams', 'description' => 'Bài thi đã được ghi nhận.'],
        'answer-review' => ['title' => 'Xem lời giải', 'frame' => 'U20', 'view' => 'pages.learner.assessment.exam-taking-and-submission.results.answer-review', 'section' => 'exams', 'description' => 'Hiểu vì sao đáp án đúng và lưu lại kiến thức hữu ích.'],
        'exam-leaderboard' => ['title' => 'Bảng xếp hạng đề thi', 'frame' => 'U21', 'view' => 'pages.learner.assessment.exam-taking-and-submission.results.leaderboard', 'section' => 'exams', 'description' => 'So sánh kết quả với những người đã hoàn thành đề.'],
        'ielts-exam-detail' => ['title' => 'IELTS Academic Full Test 02', 'frame' => 'U27', 'view' => 'pages.learner.assessment.exam-taking-and-submission.exams.show', 'section' => 'exams', 'description' => 'Thông tin bài thi IELTS Academic đầy đủ.', 'variant' => 'ielts', 'show_heading' => false],
        'ielts-rules' => ['title' => 'Hướng dẫn trước khi làm IELTS', 'frame' => 'U29', 'view' => 'pages.learner.assessment.exam-taking-and-submission.exams.rules', 'section' => 'exams', 'description' => 'Chuẩn bị cho bài thi IELTS có tính giờ.'],
    ];

    public function show(string $screen): View
    {
        return $this->renderKnownScreen($screen, self::SCREENS);
    }
}
