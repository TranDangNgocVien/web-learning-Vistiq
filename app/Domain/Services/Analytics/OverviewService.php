<?php

namespace VistiqCore\Services\Analytics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OverviewService
{
    public function read(int $days = 14): array
    {
        $end = now()->addDay()->startOfDay();
        $start = $end->copy()->subDays($days);
        $previous = $start->copy()->subDays($days);
        $attempts = DB::table('exam_attempts as a')->whereNotNull('a.snapshot')
            ->where(fn ($q) => $q->whereNull('a.snapshot->is_test')->orWhere('a.snapshot->is_test', false))
            ->where(fn ($q) => $q->whereNull('a.status')->orWhere('a.status', '!=', 'cancelled'));
        $canResults = Gate::allows('results.view');
        $canUsers = Gate::allows('users.manage');
        $canContent = Gate::allows('content.view');
        $canAudit = Gate::allows('audit.view');
        $metrics = [];
        foreach ([
            ['users', 'Tổng người dùng', 'users', 'blue', DB::table('users'), 'created_at', $canUsers],
            ['exams', 'Tổng đề thi', 'file-check', 'green', DB::table('exam_templates'), 'created_at', $canContent],
            ['questions', 'Tổng câu hỏi', 'book-open-text', 'violet', DB::table('question_bank_items'), 'created_at', $canContent],
            ['attempts', 'Lượt làm bài', 'activity', 'orange', $attempts, 'a.started_at', $canResults],
        ] as [$key, $label, $icon, $color, $query, $column, $allowed]) {
            if (! $allowed) {
                continue;
            }
            $totals = (clone $query)->selectRaw("COUNT(*) as total, SUM(CASE WHEN {$column} >= ? AND {$column} < ? THEN 1 ELSE 0 END) as current_total, SUM(CASE WHEN {$column} >= ? AND {$column} < ? THEN 1 ELSE 0 END) as previous_total", [$start, $end, $previous, $start])->first();
            $current = (int) $totals->current_total;
            $before = (int) $totals->previous_total;
            $metrics[] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'color' => $color, 'total' => (int) $totals->total, 'current' => $current, 'change' => $before ? round(($current - $before) / $before * 100, 1) : ($current === 0 ? 0 : null)];
        }
        $series = collect();
        $distribution = collect();
        $topExams = collect();
        if ($canResults) {
            $window = (clone $attempts)->where('a.started_at', '>=', $start)->where('a.started_at', '<', $end);
            $daily = (clone $window)->leftJoin('exam_templates as e', 'e.id', '=', 'a.exam_template_id')->leftJoin('exam_types as t', 't.id', '=', 'e.exam_type_id')
                ->selectRaw("DATE(a.started_at) as day, COALESCE(t.code, 'other') as type, COUNT(*) as total")->groupByRaw("DATE(a.started_at), COALESCE(t.code, 'other')")->get();
            $distribution = $daily->groupBy('type')->map(fn ($rows, $code) => ['code' => $code, 'total' => $rows->sum('total')])->values();
            $series = collect(range(0, $days - 1))->map(function ($offset) use ($start, $daily): array {
                $day = $start->copy()->addDays($offset);

                return ['date' => $day->toDateString(), 'label' => $day->format('d/m'), 'values' => $daily->where('day', $day->toDateString())->pluck('total', 'type')->all()];
            });
            $topExams = (clone $window)->join('exam_templates as e', 'e.id', '=', 'a.exam_template_id')
                ->select('e.id', 'e.title')->selectRaw('COUNT(*) as total, SUM(CASE WHEN a.submitted_at IS NOT NULL THEN 1 ELSE 0 END) as completed')
                ->groupBy('e.id', 'e.title')->orderByDesc('total')->orderBy('e.id')->limit(5)->get();
        }
        $topics = $canContent ? DB::table('question_bank_items as q')->join('learning_item_tags as p', 'p.learning_item_id', '=', 'q.learning_item_id')->join('tags as t', 't.id', '=', 'p.tag_id')
            ->select('t.name')->selectRaw('COUNT(DISTINCT q.id) as total')->groupBy('t.id', 't.name')->orderByDesc('total')->orderBy('t.id')->limit(5)->get() : collect();
        $questionTotal = $canContent ? DB::table('question_bank_items')->count() : 0;
        $newUsers = $canUsers ? DB::table('users')->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get(['id', 'name', 'email', 'status', 'created_at']) : collect();
        $activities = $canAudit ? DB::table('audit_logs as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_id')->orderByDesc('a.id')->limit(5)->get(['a.id', 'a.action', 'a.entity_type', 'a.entity_id', 'a.result', 'a.created_at', 'u.name as actor']) : collect();

        return compact('metrics', 'series', 'distribution', 'topExams', 'topics', 'questionTotal', 'newUsers', 'activities', 'days', 'canResults', 'canContent', 'canUsers', 'canAudit') + ['timezone' => config('app.timezone'), 'updatedAt' => now()->format('H:i d/m/Y')];
    }
}
