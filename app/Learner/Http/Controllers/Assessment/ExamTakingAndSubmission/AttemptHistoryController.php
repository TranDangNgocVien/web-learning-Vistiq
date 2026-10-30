<?php

namespace VistiqLearner\Http\Controllers\Assessment\ExamTakingAndSubmission;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use VistiqCore\Services\Assessment\Attempts\AttemptQueryService;
use VistiqLearner\Http\Controllers\Controller;

class AttemptHistoryController extends Controller
{
    public function index(Request $request, AttemptQueryService $query): mixed
    {
        $filters = $request->validate(['status' => 'nullable|in:in_progress,paused,expired,submitted,pending_grading,completed', 'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')]]);

        return view('pages.learner.assessment.exam-taking-and-submission.runtime.history', ['attempts' => $query->listing($filters, $request->user()->id), 'filters' => $filters]);
    }
}
