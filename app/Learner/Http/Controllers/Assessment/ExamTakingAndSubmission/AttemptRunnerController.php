<?php

namespace VistiqLearner\Http\Controllers\Assessment\ExamTakingAndSubmission;

use Illuminate\Http\Request;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Services\Assessment\Attempts\AttemptService;
use VistiqCore\Services\Assessment\QuestionBank\MediaService;
use VistiqLearner\Http\Controllers\Controller;
use VistiqLearner\Http\Requests\Assessment\ExamTakingAndSubmission\AttemptRequest;

class AttemptRunnerController extends Controller
{
    public function index(Request $request): mixed
    {
        $catalogQuery = ExamTemplate::query()
            ->where('status', '!=', 'archived')
            ->where(fn ($query) => $query->where('draft_status', 'approved')
                ->orWhere(fn ($legacy) => $legacy->where('status', 'published')->whereNotNull('published_revision_id')));
        $examTypes = DB::table('exam_types')
            ->where('is_active', true)
            ->whereIn('id', (clone $catalogQuery)->select('exam_type_id'))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $practiceModes = DB::table('practice_modes')
            ->whereIn('id', (clone $catalogQuery)->select('practice_mode_id'))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $providers = (clone $catalogQuery)->whereNotNull('provider')->distinct()->orderBy('provider')->pluck('provider');
        $hasInternalExams = (clone $catalogQuery)->whereNull('provider')->exists();

        $filteredExams = (clone $catalogQuery)->with(['examType', 'publishedRevision']);
        $examType = $request->query('exam_type');
        if (filled($examType)) {
            $filteredExams->whereHas('examType', fn ($query) => $query->where('code', $examType));
        }

        $provider = $request->query('provider');
        if ($provider === '__internal__') {
            $filteredExams->whereNull('provider');
        } elseif (filled($provider)) {
            $filteredExams->where('provider', $provider);
        }

        $practiceMode = $request->integer('practice_mode');
        if ($practiceMode > 0) {
            $filteredExams->where('practice_mode_id', $practiceMode);
        }

        $availability = $request->query('availability');
        if ($availability === 'upcoming') {
            $filteredExams->where('opens_at', '>', now());
        } elseif ($availability === 'closed') {
            $filteredExams->whereNotNull('closes_at')->where('closes_at', '<', now());
        } elseif ($availability === 'open') {
            $filteredExams->where(fn ($query) => $query->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
                ->where(fn ($query) => $query->whereNull('closes_at')->orWhere('closes_at', '>=', now()));
        }

        $searchTerm = trim((string) $request->query('q', ''));
        if ($searchTerm !== '') {
            $filteredExams->where(function ($query) use ($searchTerm): void {
                $query->where('title', 'like', '%'.$searchTerm.'%')
                    ->orWhere('description', 'like', '%'.$searchTerm.'%')
                    ->orWhere('provider', 'like', '%'.$searchTerm.'%')
                    ->orWhereHas('examType', fn ($examTypeQuery) => $examTypeQuery->where('name', 'like', '%'.$searchTerm.'%')->orWhere('code', 'like', '%'.$searchTerm.'%'))
                    ->orWhereIn('practice_mode_id', DB::table('practice_modes')->where('name', 'like', '%'.$searchTerm.'%')->select('id'));
            });
        }

        if ($request->query('sort') === 'title') {
            $filteredExams->orderBy('title');
        } else {
            $filteredExams->orderByDesc('published_at')->orderByDesc('id');
        }

        return view('pages.learner.assessment.exam-taking-and-submission.exams.index', [
            'exams' => $filteredExams->paginate(12)->withQueryString(),
            'examTypes' => $examTypes,
            'practiceModes' => $practiceModes,
            'providers' => $providers,
            'hasInternalExams' => $hasInternalExams,
        ]);
    }

    public function start(AttemptRequest $request, ExamTemplate $exam, AttemptService $service): mixed
    {
        $attempt = $service->start($exam, $request->user());

        return $request->expectsJson() ? response()->json(['id' => $attempt->id, 'url' => route('attempts.show', $attempt)]) : redirect()->route('attempts.show', $attempt);
    }

    public function show(Request $request, ExamAttempt $attempt, AttemptService $service): mixed
    {
        $service->authorize($attempt, $request->user()->id);
        $state = $service->state($attempt);
        if ($attempt->fresh()->submitted_at) {
            return redirect()->route('attempts.result', $attempt);
        }
        $endpoints = [];
        foreach (['state', 'answers', 'events', 'pause', 'resume', 'section', 'navigate', 'submit', 'result', 'recording', 'audio'] as $action) {
            $endpoints[$action] = route('attempts.'.$action, $attempt);
        }

        return view('pages.learner.assessment.exam-taking-and-submission.runtime.runner', ['preview' => false, 'attempt' => $attempt, 'snapshot' => $state['snapshot'], 'state' => $state, 'endpoints' => $endpoints, 'warnings' => []]);
    }

    public function state(Request $request, ExamAttempt $attempt, AttemptService $service): mixed
    {
        $service->authorize($attempt, $request->user()->id);

        return response()->json($service->state($attempt));
    }

    public function answers(AttemptRequest $request, ExamAttempt $attempt, AttemptService $service): mixed
    {
        $service->authorize($attempt, $request->user()->id);

        return response()->json(['answers' => $service->saveAnswers($attempt, $request->validated('answers', []))]);
    }

    public function events(AttemptRequest $request, ExamAttempt $attempt, AttemptService $service): mixed
    {
        $service->authorize($attempt, $request->user()->id);
        abort_unless($request->filled('type'), 422);

        return response()->json($service->event($attempt, $request->validated()));
    }

    public function pause(AttemptRequest $request, ExamAttempt $attempt, AttemptService $service): mixed
    {
        $service->authorize($attempt, $request->user()->id);

        return response()->json($service->state($service->pause($attempt)));
    }

    public function resume(AttemptRequest $request, ExamAttempt $attempt, AttemptService $service): mixed
    {
        $service->authorize($attempt, $request->user()->id);

        return response()->json($service->state($service->pause($attempt, true)));
    }

    public function section(AttemptRequest $request, ExamAttempt $attempt, AttemptService $service): mixed
    {
        $service->authorize($attempt, $request->user()->id);

        return response()->json($service->state($service->nextSection($attempt)));
    }

    public function submit(AttemptRequest $request, ExamAttempt $attempt, AttemptService $service): mixed
    {
        $service->authorize($attempt, $request->user()->id);
        $service->submit($attempt);

        return response()->json(['url' => route('attempts.result', $attempt), 'result' => $service->result($attempt)]);
    }

    public function navigate(AttemptRequest $request, ExamAttempt $attempt, AttemptService $service): mixed
    {
        $service->authorize($attempt, $request->user()->id);
        abort_unless($request->filled('item_key'), 422);

        return response()->json($service->state($service->navigate($attempt, $request->validated('item_key'))));
    }

    public function result(Request $request, ExamAttempt $attempt, AttemptService $service): mixed
    {
        $service->authorize($attempt, $request->user()->id);
        $result = $service->result($attempt);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        $page = [
            'title' => $result['title'] ?? 'Kết quả bài làm',
            'section' => 'exams',
            'description' => 'Xem điểm số và phân tích bài làm.',
            'view' => 'pages.learner.assessment.exam-taking-and-submission.runtime.result',
            'shell' => 'user',
        ];

        return view('shared.demo-page', compact('page', 'result'));
    }

    public function audio(AttemptRequest $request, ExamAttempt $attempt, AttemptService $service): mixed
    {
        $service->authorize($attempt, $request->user()->id);
        abort_unless($request->filled('item_key'), 422);

        return response()->json(['url' => $service->audio($attempt, $request->validated('item_key'), $request->validated('stimulus_id'))]);
    }

    public function media(Request $request, ExamAttempt $attempt, MediaAsset $asset, AttemptService $service, MediaService $media): mixed
    {
        $service->authorize($attempt, $request->user()->id);
        $service->assetAllowed($attempt, $asset);

        if ($asset->disk === 'cloudinary') {
            $url = $media->url($asset);
            $urlParts = parse_url($url) ?: [];
            abort_unless(
                filter_var($url, FILTER_VALIDATE_URL)
                    && strtolower($urlParts['scheme'] ?? '') === 'https'
                    && strtolower($urlParts['host'] ?? '') === 'res.cloudinary.com',
                404,
                'Không tìm thấy URL ảnh Cloudinary.'
            );

            return redirect()->away($url);
        }

        return $media->diskFor($asset)->response($asset->path, null, ['Content-Type' => $asset->mime_type, 'Cache-Control' => 'private, no-store']);
    }

    public function recording(AttemptRequest $request, ExamAttempt $attempt, AttemptService $service, MediaService $media): mixed
    {
        $service->authorize($attempt, $request->user()->id);
        $attempt = $service->synchronize($attempt);
        abort_if($attempt->submitted_at || $attempt->paused_at, 409);
        abort_unless($request->hasFile('file'), 422);
        $service->recordingAllowed($attempt, $request->validated('item_key', ''));
        $asset = $media->store($request->file('file'), $request->user()->id);
        $asset->update(['metadata' => array_replace($asset->metadata ?? [], ['recording_attempt_id' => $attempt->id, 'recording_item_key' => $request->validated('item_key')])]);
        if ($asset->mime_type === 'video/webm') {
            $asset->update(['mime_type' => 'audio/webm']);
        }

        return response()->json(['asset_id' => $asset->id, 'url' => $service->assetUrl($asset, $attempt)], 201);
    }
}
