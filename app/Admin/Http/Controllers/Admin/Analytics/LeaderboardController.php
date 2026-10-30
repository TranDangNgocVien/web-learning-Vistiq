<?php

namespace VistiqAdmin\Http\Controllers\Admin\Analytics;

use VistiqAdmin\Http\Controllers\Admin\AdminPageController;
use VistiqAdmin\Http\Requests\Admin\Analytics\LeaderboardFilterRequest;
use VistiqAdmin\Http\Requests\Admin\Shared\AdminActionRequest;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Services\Analytics\ExportService;
use VistiqCore\Services\Analytics\LeaderboardService;
use VistiqCore\Services\System\AuditService;

class LeaderboardController extends AdminPageController
{
    public function index(LeaderboardFilterRequest $request, LeaderboardService $leaderboard): mixed
    {
        $examId = $request->filled('exam_id') ? (int) $request->input('exam_id') : null;
        $exam = $examId ? ExamTemplate::find($examId) : null;

        return $this->page('Bảng xếp hạng', 'pages.admin.analytics.leaderboard.workspace', [
            'rankings' => $leaderboard->rankings($exam),
            'exams' => ExamTemplate::where('status', 'published')->get(),
            'selectedExam' => $exam,
        ]);
    }

    public function export(LeaderboardFilterRequest $request, LeaderboardService $leaderboard, ExportService $export): mixed
    {
        $examId = $request->filled('exam_id') ? (int) $request->input('exam_id') : null;
        $exam = $examId ? ExamTemplate::find($examId) : null;
        $rankings = $leaderboard->exportRankings($exam);
        app(AuditService::class)->record('leaderboard.export', 'exam', $exam?->id, [], ['rows' => $rankings->count(), 'format' => $request->input('format', 'csv')]);

        return $export->download($export->leaderboardCsv($rankings, $exam), 'leaderboard-'.($exam?->id ?? 'all'), $request->input('format', 'csv'));
    }

    public function reset(AdminActionRequest $request, ExamTemplate $exam, AuditService $audit): mixed
    {
        $exam->update(['leaderboard_reset_at' => now()]);
        $audit->record('leaderboard.reset', 'exam', $exam->id);

        return back()->with('status', 'Đã mở kỳ xếp hạng mới; lịch sử bài thi được giữ.');
    }
}
