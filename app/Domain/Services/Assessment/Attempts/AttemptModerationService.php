<?php

namespace VistiqCore\Services\Assessment\Attempts;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Services\System\AuditService;

class AttemptModerationService
{
    public function __construct(private AuditService $audit) {}

    public function moderate(ExamAttempt $attempt, array $data): void
    {
        Gate::authorize('reports.review');
        $data['note'] = trim($data['note'] ?? '');
        $data = Validator::make($data, ['leaderboard_status' => 'required|in:approved,private,rejected', 'note' => 'required|string|max:2000'])->validate();
        DB::transaction(function () use ($attempt, $data): void {
            $attempt = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            abort_unless($attempt->submitted_at && in_array($attempt->status, ['submitted', 'pending_grading', 'completed'], true), 409, 'Chỉ kiểm duyệt lượt đã nộp.');
            $reports = DB::table('attempt_reports')->where('exam_attempt_id', $attempt->id)->where('status', 'pending')->lockForUpdate()->get();
            if ($attempt->leaderboard_status === $data['leaderboard_status'] && $reports->isEmpty()) {
                return;
            }
            $before = ['leaderboard_status' => $attempt->leaderboard_status, 'pending_report_ids' => $reports->pluck('id')->all()];
            $attempt->update(['leaderboard_status' => $data['leaderboard_status']]);
            if ($reports->isNotEmpty()) {
                DB::table('attempt_reports')->whereIn('id', $reports->pluck('id'))->update(['status' => 'reviewed', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'updated_at' => now()]);
            }
            $this->audit->record('attempt.moderate', 'attempt', $attempt->id, $before, ['leaderboard_status' => $attempt->leaderboard_status, 'note' => $data['note']]);
        }, 3);
    }
}
