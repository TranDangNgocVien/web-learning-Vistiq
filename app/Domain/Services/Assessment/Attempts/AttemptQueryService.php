<?php

namespace VistiqCore\Services\Assessment\Attempts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Services\Shared\CompactDatabase as DB;

class AttemptQueryService
{
    /** Owner-only summary; objective accuracy avoids averaging incompatible TOEIC/IELTS scales. */
    public function profileSummary(int $owner): array
    {
        $attempts = ExamAttempt::where('user_id', $owner)->whereNotNull('snapshot')->whereNotNull('submitted_at')->where('status', '!=', 'cancelled');
        $results = (clone $attempts)->join(DB::source('attempt_results as result'), 'result.exam_attempt_id', '=', 'exam_attempts.id');
        $totals = (clone $results)->where('result.grading_status', 'completed')->selectRaw("SUM(COALESCE(JSON_EXTRACT(result.details, '$.correct'),0) + 0) as correct_count, SUM((COALESCE(JSON_EXTRACT(result.details, '$.correct'),0) + 0) + (COALESCE(JSON_EXTRACT(result.details, '$.wrong'),0) + 0) + (COALESCE(JSON_EXTRACT(result.details, '$.unanswered'),0) + 0)) as unit_count")->first();

        return ['submitted' => $attempts->count(), 'accuracy' => ($totals->unit_count ?? 0) > 0 ? round($totals->correct_count / $totals->unit_count * 100, 1) : null,
            'recent' => $results->select('exam_attempts.*', 'result.scaled_score', 'result.grading_status')->orderByDesc('exam_attempts.submitted_at')->orderByDesc('exam_attempts.id')->limit(5)->get()];
    }

    public function listing(array $filters = [], ?int $owner = null, ?string $timezone = null): mixed
    {
        return $this->filteredQuery($filters, $owner, $timezone)
            ->orderByDesc('exam_attempts.started_at')->orderByDesc('exam_attempts.id')->paginate(20)->withQueryString();
    }

    public function adminListing(array $filters = []): mixed
    {
        return $this->filteredQuery($filters, null, null, true)
            ->orderByDesc('exam_attempts.started_at')->orderByDesc('exam_attempts.id')->paginate(20)->withQueryString();
    }

    public function adminSummary(array $filters = []): array
    {
        $query = $this->filteredQuery($filters);
        $metrics = (clone $query)
            ->setEagerLoads([])
            ->reorder()
            ->select([])
            ->selectRaw('COUNT(DISTINCT exam_attempts.id) as total, COUNT(DISTINCT CASE WHEN exam_attempts.status = ? THEN exam_attempts.id END) as completed, AVG(result.scaled_score) as average_score', ['completed'])
            ->first();
        $pendingReports = (clone $query)
            ->setEagerLoads([])
            ->whereExists(function ($report): void {
                $report->selectRaw('1')
                    ->from('attempt_reports')
                    ->whereColumn('attempt_reports.exam_attempt_id', 'exam_attempts.id')
                    ->where('attempt_reports.status', 'pending');
            })
            ->distinct()
            ->count('exam_attempts.id');
        $scoreScales = (clone $query)
            ->setEagerLoads([])
            ->join(DB::source('exam_templates as template'), 'template.id', '=', 'exam_attempts.exam_template_id')
            ->whereNotNull('result.scaled_score')
            ->select('template.score_scale_id')
            ->distinct()
            ->pluck('template.score_scale_id');
        $knownScoreScales = $scoreScales->filter(static fn ($scaleId): bool => $scaleId !== null)->unique()->values();
        $hasUnknownScoreScale = $scoreScales->contains(static fn ($scaleId): bool => $scaleId === null);
        $averageScore = null;

        if ($knownScoreScales->count() === 1 && ! $hasUnknownScoreScale) {
            $averageScore = $metrics?->average_score;
            $averageContext = 'Trên cùng thang điểm';
        } elseif ($knownScoreScales->count() > 1) {
            $averageContext = 'Nhiều thang điểm · không gộp';
        } elseif ($hasUnknownScoreScale) {
            $averageContext = 'Có điểm chưa rõ thang';
        } else {
            $averageContext = 'Chưa có điểm đã chấm';
        }

        $total = (int) ($metrics?->total ?? 0);
        $completed = (int) ($metrics?->completed ?? 0);

        return [
            'total' => $total,
            'completed' => $completed,
            'completion_rate' => $total > 0 ? round($completed / $total * 100, 1) : null,
            'average_score' => $averageScore !== null ? (float) $averageScore : null,
            'average_context' => $averageContext,
            'pending_reports' => (int) $pendingReports,
        ];
    }

    private function filteredQuery(array $filters = [], ?int $owner = null, ?string $timezone = null, bool $includeReportCounts = false): Builder
    {
        $query = ExamAttempt::with(['user', 'exam'])
            ->select('exam_attempts.*')
            ->leftJoin(DB::source('attempt_results as result'), 'result.exam_attempt_id', '=', 'exam_attempts.id')
            ->addSelect('result.scaled_score', 'result.grading_status');

        if ($includeReportCounts) {
            $query->addSelect([
                'report_count' => DB::table('attempt_reports')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('attempt_reports.exam_attempt_id', 'exam_attempts.id'),
                'pending_report_count' => DB::table('attempt_reports')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('attempt_reports.exam_attempt_id', 'exam_attempts.id')
                    ->where('attempt_reports.status', 'pending'),
            ]);
        }

        if ($owner !== null) {
            $query->where('exam_attempts.user_id', $owner);
        }
        foreach (['user' => 'user_id', 'exam' => 'exam_template_id', 'status' => 'status'] as $filter => $column) {
            if (isset($filters[$filter])) {
                $query->where('exam_attempts.'.$column, $filters[$filter]);
            }
        }
        if (isset($filters['from'])) {
            $query->where('exam_attempts.started_at', '>=', Carbon::parse($filters['from'], $timezone ?? config('app.timezone'))->startOfDay()->setTimezone(config('app.timezone'))->toDateTimeString());
        }
        if (isset($filters['to'])) {
            $query->where('exam_attempts.started_at', '<', Carbon::parse($filters['to'], $timezone ?? config('app.timezone'))->addDay()->startOfDay()->setTimezone(config('app.timezone'))->toDateTimeString());
        }
        if (isset($filters['min_score'])) {
            $query->where('result.scaled_score', '>=', $filters['min_score']);
        }
        if (isset($filters['max_score'])) {
            $query->where('result.scaled_score', '<=', $filters['max_score']);
        }
        if (isset($filters['reports'])) {
            $method = $filters['reports'] === 'none' ? 'whereNotExists' : 'whereExists';
            $query->$method(function ($report) use ($filters): void {
                $report->selectRaw('1')->from('attempt_reports')->whereColumn('attempt_reports.exam_attempt_id', 'exam_attempts.id');
                if ($filters['reports'] === 'pending') {
                    $report->where('attempt_reports.status', 'pending');
                }
            });
        }

        return $query;
    }

    public function detail(ExamAttempt $attempt): array
    {
        $attempt->load(['user', 'exam', 'answers']);
        $events = DB::table('attempt_events')->join(DB::source('attempt_event_types'), 'attempt_event_types.id', '=', 'attempt_events.attempt_event_type_id')->where('exam_attempt_id', $attempt->id)->orderBy('occurred_at')->orderBy('attempt_events.id')->get();
        $reports = DB::table('attempt_reports')->where('exam_attempt_id', $attempt->id)->orderBy('id')->get();
        $result = DB::table('attempt_results')->where('exam_attempt_id', $attempt->id)->first();
        $answerIds = $attempt->answers->pluck('id');
        $history = DB::table('audit_logs')->where(function ($query) use ($attempt, $answerIds): void {
            $query->where(fn ($q) => $q->where('entity_type', 'attempt')->where('entity_id', $attempt->id)->where('action', 'attempt.moderate'))
                ->orWhere(fn ($q) => $q->where('entity_type', 'answer')->whereIn('entity_id', $answerIds)->where('action', 'answer.grade'));
        })->orderByDesc('id')->paginate(30, ['*'], 'audit_page')->withQueryString();

        return compact('attempt', 'events', 'reports', 'result', 'history');
    }
}
