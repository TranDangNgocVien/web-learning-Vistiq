<?php

namespace VistiqAdmin\Http\Controllers\Admin\Assessment\Attempts;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VistiqAdmin\Http\Controllers\Admin\AdminPageController;
use VistiqAdmin\Http\Requests\Admin\Assessment\Attempts\ManualGradeRequest;
use VistiqAdmin\Http\Requests\Admin\Shared\AdminActionRequest;
use VistiqCore\Models\AttemptAnswer;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Services\Assessment\Attempts\GradingService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;
use VistiqCore\Services\Shared\AiService;

class GradingController extends AdminPageController
{
    public function index(Request $request): mixed
    {
        Gate::authorize('results.grade');
        $attemptId = $request->filled('attempt')
            ? (int) $request->validate(['attempt' => 'required|integer|min:1|exists:exam_attempts,id'])['attempt']
            : null;
        $mysql = DB::connection()->getDriverName() === 'mysql';
        $items = $mysql
            ? "JSON_TABLE(grading_attempt.snapshot, '$.items[*]' COLUMNS (item_key VARCHAR(80) PATH '$.key', question_type VARCHAR(40) PATH '$.type')) as manual_item"
            : "json_each(grading_attempt.snapshot, '$.items') as manual_item";
        $predicate = $mysql
            ? 'manual_item.item_key = attempt_answers.item_key AND manual_item.question_type IN (?, ?)'
            : "json_extract(manual_item.value, '$.key') = attempt_answers.item_key AND json_extract(manual_item.value, '$.type') IN (?, ?)";
        // Join the snapshot rows directly. MySQL can return inconsistent count/data
        // results when the correlated JSON_TABLE EXISTS is combined with ORDER BY.
        $query = AttemptAnswer::select('attempt_answers.*')
            ->join('exam_attempts as grading_attempt', 'grading_attempt.id', '=', 'attempt_answers.exam_attempt_id')
            ->join(DB::raw($items), fn ($join) => $join->whereRaw($predicate, ['essay', 'speaking']))
            ->whereNotNull('attempt_answers.item_key')->whereNotNull('grading_attempt.submitted_at')
            ->when($attemptId, fn ($query) => $query->where('attempt_answers.exam_attempt_id', $attemptId));
        $answerId = $request->validate(['answer' => 'sometimes|integer|min:1'])['answer'] ?? null;
        $page = (int) $request->input('page', 1);
        if ($answerId && ! $request->has('page')) {
            $selected = (clone $query)->where('attempt_answers.id', $answerId)->first(['attempt_answers.id', 'attempt_answers.created_at']);
            abort_unless($selected, 404);
            $page = (clone $query)->where(function ($earlier) use ($selected): void {
                $earlier->where('attempt_answers.created_at', '>', $selected->created_at)
                    ->orWhere(function ($sameTime) use ($selected): void {
                        $sameTime->where('attempt_answers.created_at', $selected->created_at)
                            ->where('attempt_answers.id', '>', $selected->id);
                    });
            })->count() + 1;
        }
        $answers = $query->with('attempt.user')->orderByDesc('attempt_answers.created_at')
            ->orderByDesc('attempt_answers.id')->paginate(1, ['attempt_answers.*'], 'page', $page)
            ->appends(array_filter(['attempt' => $attemptId]));
        $suggestions = DB::table('ai_tasks')->whereIn('attempt_answer_id', $answers->pluck('id'))->latest()->get()->groupBy('attempt_answer_id');

        $gradingCriteria = ['essay' => QuestionRegistry::WRITING_CRITERIA, 'speaking' => QuestionRegistry::SPEAKING_CRITERIA];

        return $this->page('Chấm điểm bài làm', 'pages.admin.assessment.attempts.workspace.grading', compact('answers', 'suggestions', 'gradingCriteria', 'attemptId'));
    }

    public function store(ManualGradeRequest $request, AttemptAnswer $answer, GradingService $grading): mixed
    {
        $wasGraded = (bool) $answer->grade;
        $grading->grade($answer, $request->validated());

        return redirect()->route('admin.grading.index', ['attempt' => $answer->exam_attempt_id, 'answer' => $answer->id])
            ->with('status', $wasGraded ? 'Đã lưu điểm điều chỉnh và cập nhật kết quả.' : 'Đã lưu điểm và cập nhật kết quả.');
    }

    public function ai(AdminActionRequest $request, AttemptAnswer $answer, AiService $ai): mixed
    {
        Gate::authorize('results.grade');
        $attempt = ExamAttempt::findOrFail($answer->exam_attempt_id);
        abort_unless($attempt->submitted_at, 409);
        $item = collect($attempt->snapshot['items'])->firstWhere('key', $answer->item_key);
        abort_unless($item && in_array($item['type'], ['essay', 'speaking'], true), 422);
        $id = $ai->enqueue($item['type'] === 'essay' ? 'writing_evaluation' : 'speaking_evaluation', ['item' => $item, 'response' => $answer->response], $request->user()->id, $answer->id);

        return back()->with('status', 'Đã đưa gợi ý AI #'.$id.' vào hàng đợi. Reviewer công bố điểm sau khi kiểm tra.');
    }
}
