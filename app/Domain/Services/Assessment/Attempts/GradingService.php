<?php

namespace VistiqCore\Services\Assessment\Attempts;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use VistiqCore\Models\AttemptAnswer;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;
use VistiqCore\Services\System\AuditService;

class GradingService
{
    public function __construct(private ScoringService $scoring, private AuditService $audit) {}

    public function grade(AttemptAnswer $answer, array $data): void
    {
        Gate::authorize('results.grade');
        DB::transaction(function () use ($answer, $data): void {
            $attempt = ExamAttempt::lockForUpdate()->findOrFail($answer->exam_attempt_id);
            $answer = AttemptAnswer::lockForUpdate()->findOrFail($answer->id);
            abort_unless($attempt->submitted_at, 409, 'Bài chưa nộp.');
            $item = collect($attempt->snapshot['items'])->firstWhere('key', $answer->item_key);
            abort_unless($item && in_array($item['type'], ['essay', 'speaking'], true), 422);
            $data['reason'] = trim($data['reason'] ?? '');
            $data = Validator::make($data, ['criteria' => 'required|array|size:4', 'criteria.*' => 'required|numeric|min:0|max:9', 'criterion_feedback' => 'sometimes|array', 'criterion_feedback.*' => 'nullable|string|max:2000', 'improvements' => 'nullable|string|max:10000', 'feedback' => 'required|string|max:20000', 'reason' => ($answer->grade ? 'required' : 'nullable').'|string|max:2000', 'grade_token' => ($answer->grade ? 'required' : 'nullable').'|string|size:64'])->validate();
            if (isset($data['grade_token'])) {
                abort_unless(hash_equals(hash('sha256', json_encode($answer->grade ?? [], JSON_THROW_ON_ERROR)), $data['grade_token']), 409, 'Điểm đã được reviewer khác cập nhật. Tải lại trước khi chấm.');
            }
            $criteria = $item['type'] === 'essay' ? QuestionRegistry::WRITING_CRITERIA : QuestionRegistry::SPEAKING_CRITERIA;
            if (array_diff($criteria, array_keys($data['criteria'])) || array_diff(array_keys($data['criteria']), $criteria)) {
                throw ValidationException::withMessages(['criteria' => 'Cần đủ bốn tiêu chí của rubric.']);
            }
            if (array_diff(array_keys($data['criterion_feedback'] ?? []), $criteria)) {
                throw ValidationException::withMessages(['criterion_feedback' => 'Nhận xét phải thuộc tiêu chí của rubric.']);
            }
            foreach ($data['criteria'] as $score) {
                if ($score < 0 || $score > 9 || fmod((float) $score * 2, 1) !== 0.0) {
                    throw ValidationException::withMessages(['criteria' => 'Điểm từ 0 đến 9, bước 0.5.']);
                }
            }
            $before = $answer->grade ?? [];
            $answer->update(['grade' => ['criteria' => $data['criteria'], 'criterion_feedback' => $data['criterion_feedback'] ?? [], 'improvements' => $data['improvements'] ?? null, 'feedback' => $data['feedback'], 'reason' => $data['reason'] ?: null, 'reviewer_id' => auth()->id(), 'estimated' => true, 'graded_at' => now()->toIso8601String()]]);
            $this->audit->record('answer.grade', 'answer', $answer->id, $before, $answer->grade);
            $this->scoring->calculate($attempt);
        }, 3);
    }
}
