<?php

namespace VistiqCore\Services\Analytics;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Validation\ValidationException;
use VistiqCore\Models\ExamTemplate;

class LeaderboardService
{
    private function rankedQuery(?ExamTemplate $exam = null): Builder
    {
        $seconds = "(COALESCE(JSON_EXTRACT(r.details, '$.duration_seconds'), 0) + 0)";
        $eligible = DB::table('exam_attempts as a')->join(DB::source('attempt_results as r'), 'r.exam_attempt_id', '=', 'a.id')->join('users as u', 'u.id', '=', 'a.user_id')->join('exam_templates as e', 'e.id', '=', 'a.exam_template_id')
            ->whereNotNull('a.snapshot')->whereNotNull('a.submitted_at')->whereIn('a.status', ['submitted', 'completed'])
            ->where('a.snapshot->policies->leaderboard', true)
            ->where('a.leaderboard_status', 'approved')->where('r.grading_status', 'completed')->whereNotNull('r.scaled_score')->where('u.status', 'active')
            ->where(fn ($q) => $q->whereNull('e.leaderboard_reset_at')->orWhereColumn('a.submitted_at', '>', 'e.leaderboard_reset_at'))
            ->when($exam, fn ($q) => $q->where('a.exam_template_id', $exam->id))
            ->select('a.id', 'a.exam_template_id', 'a.user_id', 'a.snapshot', 'a.submitted_at', 'u.name', 'e.title', 'r.scaled_score')
            ->selectRaw("$seconds as seconds")
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY a.exam_template_id, a.user_id ORDER BY r.scaled_score DESC, $seconds ASC, a.submitted_at ASC, a.id ASC) as best_attempt");
        $best = DB::query()->fromSub($eligible, 'eligible')->where('best_attempt', 1);
        $ranked = DB::query()->fromSub($best, 'best')->select('best.*')
            ->selectRaw('RANK() OVER (PARTITION BY exam_template_id ORDER BY scaled_score DESC, seconds ASC) as rank_position');

        return DB::query()->fromSub($ranked, 'ranked')
            ->orderByDesc('scaled_score')->orderBy('seconds')->orderBy('submitted_at')->orderBy('id');
    }

    private function present(object $row, ?int $viewerId = null): object
    {
        $snapshot = json_decode($row->snapshot, true);
        $isCurrentViewer = $viewerId !== null && (int) $row->user_id === $viewerId;
        $row->name = ($snapshot['policies']['anonymous'] ?? true) && ! $isCurrentViewer
            ? 'Học viên ẩn danh'
            : $row->name;

        return $row;
    }

    private function paginatedRankings(?ExamTemplate $exam = null, ?int $viewerId = null): mixed
    {
        return $this->rankedQuery($exam)->paginate(30)->withQueryString()->through(fn ($row) => $this->present($row, $viewerId));
    }

    public function rankings(?ExamTemplate $exam = null): mixed
    {
        return $this->paginatedRankings($exam);
    }

    public function bestRankForUser(int $owner): ?array
    {
        $row = $this->rankedQuery()->where('user_id', $owner)->whereIn('exam_template_id', ExamTemplate::where('status', 'published')->whereNotNull('published_revision_id')->select('id'))
            ->reorder('rank_position')->orderByDesc('submitted_at')->first();

        return $row ? ['rank' => (int) $row->rank_position, 'exam_id' => (int) $row->exam_template_id, 'title' => $row->title] : null;
    }

    public function exportRankings(?ExamTemplate $exam = null): Collection
    {
        $rows = $this->rankedQuery($exam)->limit(ExportService::MAX_ROWS + 1)->get();
        if ($rows->count() > ExportService::MAX_ROWS) {
            throw ValidationException::withMessages(['export' => 'Báo cáo vượt 10.000 dòng. Hãy chọn một đề hoặc thu hẹp phạm vi.']);
        }

        return $rows->map(fn ($row) => $this->present($row));
    }

    /** Public response excludes internal IDs, account fields and snapshots. */
    public function publicRankings(ExamTemplate $exam, int $viewerId): mixed
    {
        return $this->paginatedRankings($exam, $viewerId)->through(fn ($row) => [
            'rank' => (int) $row->rank_position, 'name' => $row->name,
            'score' => (float) $row->scaled_score, 'seconds' => (int) $row->seconds,
            'is_current_user' => (int) $row->user_id === $viewerId,
        ]);
    }
}
