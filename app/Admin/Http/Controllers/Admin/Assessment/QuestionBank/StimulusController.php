<?php

namespace VistiqAdmin\Http\Controllers\Admin\Assessment\QuestionBank;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use VistiqAdmin\Http\Controllers\Admin\AdminPageController;
use VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank\AudioBatchUploadRequest;
use VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank\AudioBulkDeleteRequest;
use VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank\ReadingReviewRequest;
use VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank\StimulusStoreRequest;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\QuestionDifficulty;
use VistiqCore\Models\QuestionType;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Assessment\QuestionBank\AudioQuestionService;
use VistiqCore\Services\Assessment\QuestionBank\MediaService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionBankCatalog;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;
use VistiqCore\Services\Assessment\QuestionBank\ResourceLibraryService;
use VistiqCore\Services\System\AuditService;

class StimulusController extends AdminPageController
{
    public function listening(Request $request): mixed
    {
        $catalog = app(QuestionBankCatalog::class);
        $bankData = $catalog->catalog();
        $search = mb_strtolower(trim($request->string('q')->toString()));
        $status = $request->string('status')->toString();
        $exam = $request->string('exam')->toString();
        $partId = $request->integer('part');
        $audio = in_array($request->input('audio'), ['yes', 'no'], true) ? $request->input('audio') : '';
        $withAudio = static function (array $entry) use ($bankData): array {
            $metadata = $entry['payload']['metadata'] ?? [];
            $assetId = (int) ($metadata['asset_id'] ?? $metadata['audio_id'] ?? 0);
            $asset = $bankData['assets']->get($assetId);
            $assetPath = $entry['payload']['asset_path'] ?? '';
            $assetMetadata = $asset?->metadata ?? [];
            $duration = (int) ($metadata['duration_seconds'] ?? ($assetMetadata['duration_seconds'] ?? 0));
            $firstQuestionId = $entry['questions']->keys()->first();
            $questionMetadata = $firstQuestionId ? ($bankData['records'][$firstQuestionId]['payload']['metadata'] ?? []) : [];
            $audioGroupCode = trim((string) ($metadata['audio_group'] ?? $questionMetadata['audio_group'] ?? ''));

            return $entry + [
                'audio_asset' => $asset,
                'audio_id' => $assetId,
                'audio_group_code' => $audioGroupCode,
                'audio_url' => $asset
                    ? route($asset->disk === 'local' ? 'admin.media.waveform' : 'admin.media.show', $asset)
                    : $assetPath,
                'has_audio' => $asset !== null || filled($assetPath),
                'duration_seconds' => $duration,
            ];
        };
        $allLessons = $bankData['entries']->filter(fn (array $entry): bool => $entry['kind'] === 'group'
            && filled($entry['payload']['metadata']['lesson_code'] ?? null)
            && $entry['questions']->isNotEmpty()
            && $entry['parts']->contains(fn ($part): bool => $part->skill?->code === 'listening')
        )->map($withAudio)->values();
        $rows = $allLessons->filter(fn (array $entry): bool => ($search === '' || str_contains(mb_strtolower($entry['code'].' '.$entry['title']), $search))
            && ($status === '' || $entry['question_status'] === $status)
            && ($exam === '' || $entry['parts']->contains(fn ($part): bool => $part->examType?->code === $exam))
            && (! $partId || $entry['parts']->contains('id', $partId))
            && ($audio === '' || ($audio === 'yes') === $entry['has_audio'])
        )->sortByDesc('updated_at')->values();
        $perPage = in_array($request->input('per_page'), ['10', '20', '50', 10, 20, 50], true) ? (int) $request->input('per_page') : 10;
        $lastPage = max(1, (int) ceil($rows->count() / $perPage));
        $page = min(max(1, $request->integer('page', 1)), $lastPage);
        $lessons = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => route('admin.questions.listening'), 'query' => $request->query()]);
        $entry = $request->integer('group') ? $catalog->entryFor('group', $request->integer('group')) : null;
        abort_if($entry && empty($entry['payload']['metadata']['lesson_code']), 404);
        $entry = $entry ? $withAudio($entry) : null;
        $requestedQuestionId = $request->integer('question');
        $selectedQuestion = $entry
            ? ($requestedQuestionId ? $entry['questions']->get($requestedQuestionId) : $entry['questions']->first())
            : null;
        abort_if($requestedQuestionId && ! $selectedQuestion, 404);
        $questionIds = $entry ? $entry['questions']->keys()->values() : collect();
        $selectedQuestionIndex = $selectedQuestion ? $questionIds->search($selectedQuestion->id) + 1 : 0;
        $listeningQuestions = collect($bankData['records'])->filter(fn (array $record): bool => $record['question']->examPart?->skill?->code === 'listening');
        $audioGroupCount = $bankData['entries']->filter(fn (array $candidate): bool => $candidate['kind'] === 'group'
            && $candidate['parts']->contains(fn ($part): bool => $part->skill?->code === 'listening')
        )->count();
        $examOptions = $allLessons->flatMap(fn (array $lesson) => $lesson['parts']->map(fn ($part): array => [
            'code' => $part->examType?->code,
            'name' => $part->examType?->name,
        ]))->filter(fn (array $option): bool => filled($option['code']))->unique('code')->values();
        $partOptions = $allLessons->flatMap(fn (array $lesson) => $lesson['parts']->map(fn ($part): array => [
            'id' => (int) $part->id,
            'name' => $part->name,
            'exam' => $part->examType?->name,
        ]))->unique('id')->sortBy(fn (array $option): string => $option['exam'].' '.$option['name'])->values();

        return $this->page('Câu hỏi Listening', 'pages.admin.assessment.question-bank.workspace.listening-bank', [
            'standaloneQuestions' => $this->standaloneQuestions($request, $bankData, $allLessons, 'listening'),
            'lessons' => $lessons,
            'entry' => $entry,
            'bankData' => $bankData,
            'search' => $request->string('q')->toString(),
            'statusFilter' => $status,
            'examFilter' => $exam,
            'partFilter' => $partId,
            'audioFilter' => $audio,
            'examOptions' => $examOptions,
            'partOptions' => $partOptions,
            'selectedQuestion' => $selectedQuestion,
            'selectedQuestionIndex' => $selectedQuestionIndex,
            'selectedQuestionPayload' => $selectedQuestion ? $bankData['records'][$selectedQuestion->id]['payload'] : null,
            'listeningStats' => [
                'lessons' => $allLessons->count(),
                'groups' => $audioGroupCount,
                'questions' => $listeningQuestions->count(),
                'approved' => $listeningQuestions->whereIn('status', ['approved', 'published'])->count(),
            ],
        ]);
    }

    public function reading(Request $request, AudioQuestionService $audioQuestions): mixed
    {
        $catalog = app(QuestionBankCatalog::class);
        $bankData = $catalog->catalog();
        $search = mb_strtolower(trim($request->string('q')->toString()));
        $exam = $request->string('exam')->toString();
        $partId = $request->integer('part');
        $status = $request->string('status')->toString();
        $readingEntries = $bankData['entries']->filter(fn (array $entry): bool => $entry['kind'] === 'stimulus' && $entry['stimulus']->stimulus_type === 'reading')->sortByDesc('updated_at')->values();
        $readingQuestions = collect($bankData['records'])->filter(fn (array $record): bool => $record['question']->examPart?->skill?->code === 'reading');
        $parts = ExamPart::with(['examType', 'skill'])->whereHas('skill', fn ($query) => $query->where('code', 'reading'))->get();
        $examOptions = $parts->pluck('examType')->filter()->unique('id')->sortBy('name')->values();
        $rows = $readingEntries
            ->filter(fn (array $entry): bool => ($search === '' || str_contains(mb_strtolower($entry['code'].' '.$entry['title'].' '.strip_tags($entry['payload']['body'] ?? '')), $search))
                && ($exam === '' || $entry['parts']->contains(fn ($part): bool => $part->examType?->code === $exam))
                && (! $partId || $entry['parts']->contains('id', $partId))
                && ($status === '' || $entry['status'] === $status)
            )
            ->sortByDesc('updated_at')->values();
        $perPage = in_array($request->input('per_page'), ['10', '20', '50', 10, 20, 50], true) ? (int) $request->input('per_page') : 10;
        $lastPage = max(1, (int) ceil($rows->count() / $perPage));
        $page = min(max(1, $request->integer('page', 1)), $lastPage);
        $readings = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => route('admin.readings.index'), 'query' => $request->query()]);
        $entry = $request->integer('stimulus') ? $catalog->entryFor('stimulus', $request->integer('stimulus')) : null;
        abort_if($entry && $entry['stimulus']->stimulus_type !== 'reading', 404);
        $requestedQuestionId = $request->integer('preview_question');
        $selectedQuestion = $entry
            ? ($requestedQuestionId ? $entry['questions']->get($requestedQuestionId) : $entry['questions']->first())
            : null;
        abort_if($requestedQuestionId && $entry && ! $selectedQuestion, 404);
        $questionIds = $entry ? $entry['questions']->keys()->values() : collect();
        $selectedQuestionIndex = $selectedQuestion ? $questionIds->search($selectedQuestion->id) + 1 : 0;

        return $this->page('Câu hỏi Reading', 'pages.admin.assessment.question-bank.workspace.readings', [
            'standaloneQuestions' => $this->standaloneQuestions($request, $bankData, $readingEntries, 'reading'),
            'readings' => $readings,
            'entry' => $entry,
            'bankData' => $bankData,
            'search' => $request->string('q')->toString(),
            'examFilter' => $exam,
            'examOptions' => $examOptions,
            'partId' => $partId,
            'statusFilter' => $status,
            'parts' => $parts,
            'selectedQuestion' => $selectedQuestion,
            'selectedQuestionIndex' => $selectedQuestionIndex,
            'selectedQuestionPayload' => $selectedQuestion ? $bankData['records'][$selectedQuestion->id]['payload'] : null,
            'readingStats' => [
                'readings' => $readingEntries->count(),
                'groups' => $readingQuestions->map(fn (array $record): int => (int) ($record['payload']['group_id'] ?? 0))->filter()->unique()->count(),
                'questions' => $readingQuestions->count(),
                'approved' => $readingQuestions->whereIn('status', ['approved', 'published'])->count(),
            ],
            'creating' => $request->boolean('create'),
            'importQuestionTypeId' => QuestionType::where('code', 'multiple_choice')->value('id'),
            'importDifficultyId' => QuestionDifficulty::where('code', 'medium')->value('id'),
            'workflowRevision' => $entry ? ContentRevision::find($entry['stimulus']->draft_revision_id) : null,
        ]);
    }

    public function questionEditor(Request $request, LearningStimulus $stimulus): mixed
    {
        abort_unless($stimulus->stimulus_type === 'reading', 404);
        $request->validate(['question' => 'nullable|integer|exists:question_bank_items,id']);
        $entry = app(QuestionBankCatalog::class)->entryFor('stimulus', $stimulus->id);
        $question = $request->integer('question') ? $entry['questions']->get($request->integer('question')) : null;
        abort_if($request->integer('question') && ! $question, 404);
        $data = app(QuestionController::class)->editorData($question, $stimulus);

        return view('pages.admin.assessment.question-bank.questions.editor', $data + ['embeddedStimulus' => $stimulus]);
    }

    public function audio(Request $request, AudioQuestionService $audioQuestions): mixed
    {
        if (! $request->filled('stimulus')) {
            $request->merge(['type' => 'audio']);

            return app(MediaController::class)->index($request, app(ResourceLibraryService::class));
        }

        return $this->index($request, 'audio', $audioQuestions);
    }

    private function index(Request $request, string $type, AudioQuestionService $audioQuestions): mixed
    {
        $search = $request->string('q')->toString();
        $rows = LearningStimulus::withCount('questions')->where('stimulus_type', $type)->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))->latest()->paginate(15)->withQueryString();
        $selected = $request->integer('stimulus') ? LearningStimulus::findOrFail($request->integer('stimulus')) : null;

        if ($type === 'audio') {
            $rows->getCollection()->each(function (LearningStimulus $stimulus) use ($audioQuestions): void {
                $stimulus->setAttribute('questions_count', $audioQuestions->questionsForStimulus($stimulus)->count());
            });

            if ($selected) {
                $selected->setRelation('questions', $audioQuestions->questionsForStimulus($selected));
            }
        } elseif ($selected) {
            $selected->load('questions');
        }

        return $this->page($type === 'reading' ? 'Passage' : 'Audio', 'pages.admin.assessment.question-bank.workspace.stimulus-editor', [
            'stimulusType' => $type,
            'stimuli' => $rows,
            'selectedStimulus' => $selected,
            'workflowRevision' => $selected ? ContentRevision::find($selected->draft_revision_id) : null,
            'search' => $search,
            'assetUrl' => $selected ? null : null,
            'payload' => $selected ? app(ContentRevisionService::class)->payload('stimulus', $selected) : [],
            'assets' => MediaAsset::latest()->limit(200)->get(),
        ]);
    }

    public function storeReading(StimulusStoreRequest $request): mixed
    {
        return $this->store($request, 'reading');
    }

    public function reviewReadings(ReadingReviewRequest $request, ContentRevisionService $revisions): mixed
    {
        $data = $request->validated();
        $ids = array_map('intval', $data['ids']);
        $revisionIds = $data['revision_ids'];

        DB::transaction(function () use ($ids, $revisionIds, $revisions): void {
            $readings = LearningStimulus::query()
                ->where('stimulus_type', 'reading')
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            abort_if($readings->count() !== count($ids), 409, 'Một hoặc nhiều bài Reading không còn tồn tại. Hãy tải lại danh sách.');

            foreach ($ids as $id) {
                $reading = $readings->get($id);
                $revisionId = (int) ($revisionIds[$id] ?? 0);
                $revision = $reading->draft_revision_id
                    ? ContentRevision::query()
                        ->where('kind', 'stimulus')
                        ->where('entity_id', $reading->id)
                        ->whereKey($reading->draft_revision_id)
                        ->first()
                    : null;

                abort_if(! $revision || $revision->id !== $revisionId || $revision->status !== 'draft', 409, 'Một hoặc nhiều bài đã thay đổi hoặc không còn là bản nháp. Hãy tải lại danh sách rồi chọn lại.');
            }

            foreach ($ids as $id) {
                $revisions->transition('stimulus', $readings->get($id), 'review');
            }
        });

        $count = count($ids);

        return back()->with('status', "Đã gửi duyệt {$count} bài Reading.");
    }

    public function storeAudio(StimulusStoreRequest $request): mixed
    {
        return $this->store($request, 'audio');
    }

    public function storeAudioBatch(AudioBatchUploadRequest $request, MediaService $media, ContentRevisionService $revisions, AuditService $audit): mixed
    {
        $uploaded = 0;

        foreach ($request->file('files', []) as $file) {
            $title = trim((string) pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $title = $title !== '' ? $title : 'Audio '.($uploaded + 1);

            DB::transaction(function () use ($file, $title, $media, $revisions, $audit): void {
                $asset = $media->store($file, auth()->id());
                $stimulus = LearningStimulus::create([
                    'created_by' => auth()->id(),
                    'title' => $title,
                    'stimulus_type' => 'audio',
                    'body' => '',
                    'status' => 'draft',
                ]);
                $revisions->save('stimulus', $stimulus, [
                    'title' => $title,
                    'stimulus_type' => 'audio',
                    'body' => '',
                    'transcript' => '',
                    'asset_path' => null,
                    'duration_seconds' => null,
                    'accent' => '',
                    'metadata' => ['asset_id' => $asset->id],
                ], 0);
                $audit->record('stimulus.audio.upload', 'stimulus', $stimulus->id, [], [
                    'asset_id' => $asset->id,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            });

            $uploaded++;
        }

        $message = "Đã tải lên {$uploaded} file audio.";

        return $request->expectsJson()
            ? response()->json(['uploaded' => $uploaded, 'message' => $message])
            : redirect()->route('admin.audio.index')->with('status', $message);
    }

    public function destroyAudio(AudioBulkDeleteRequest $request, ContentRevisionService $revisions, MediaService $media): mixed
    {
        $deleted = 0;
        $skipped = 0;
        $data = $request->validated();
        $ids = filter_var($data['select_all'] ?? false, FILTER_VALIDATE_BOOLEAN)
            ? LearningStimulus::query()
                ->where('stimulus_type', 'audio')
                ->when(filled($data['q'] ?? null), fn ($query) => $query->where('title', 'like', '%'.$data['q'].'%'))
                ->pluck('id')
                ->all()
            : ($data['ids'] ?? []);

        abort_unless($ids !== [], 422, 'Hãy chọn ít nhất một audio cần xóa.');

        foreach ($ids as $id) {
            $stimulus = LearningStimulus::query()->where('stimulus_type', 'audio')->find($id);

            if (! $stimulus) {
                $skipped++;

                continue;
            }

            $assetIds = $this->audioAssetIds($stimulus);

            try {
                $revisions->delete('stimulus', $stimulus);
                $deleted++;
            } catch (HttpExceptionInterface $exception) {
                if ($exception->getStatusCode() !== 409) {
                    throw $exception;
                }

                $skipped++;

                continue;
            }

            foreach ($assetIds as $assetId) {
                if (! $this->assetIsReferenced($assetId)) {
                    $asset = MediaAsset::find($assetId);
                    if ($asset) {
                        $media->delete($asset);
                    }
                }
            }
        }

        $message = "Đã xóa {$deleted} audio; bỏ qua {$skipped} audio đang được dùng hoặc không còn là bản nháp.";

        return $request->expectsJson()
            ? response()->json(['deleted' => $deleted, 'skipped' => $skipped, 'message' => $message])
            : back()->with('status', $message);
    }

    private function store(StimulusStoreRequest $request, string $type): mixed
    {
        $stimulus = DB::transaction(function () use ($request, $type) {
            $data = $this->payload($request);
            $data['stimulus_type'] = $type;
            $root = LearningStimulus::create(['created_by' => auth()->id(), 'title' => $data['title'], 'stimulus_type' => $type, 'body' => app(QuestionRegistry::class)->sanitize($data['body'] ?? ''), 'status' => 'draft']);
            if ($type === 'reading' && ! empty($data['metadata']['exam_part_id'])) {
                $data['metadata']['question_group_id'] = app(QuestionController::class)->createStimulusGroup($root, (int) $data['metadata']['exam_part_id']);
            }
            app(ContentRevisionService::class)->save('stimulus', $root, $data, 0);

            return $root;
        });

        return redirect()->route($type === 'reading' ? 'admin.readings.index' : 'admin.audio.index', ['stimulus' => $stimulus->id])->with('status', 'Đã lưu tài nguyên bản nháp.');
    }

    public function update(StimulusStoreRequest $request, LearningStimulus $stimulus, ContentRevisionService $revisions): mixed
    {
        $revision = DB::transaction(function () use ($request, $stimulus, $revisions) {
            $locked = LearningStimulus::query()->lockForUpdate()->findOrFail($stimulus->id);
            $request->route()->setParameter('stimulus', $locked);

            return $revisions->save('stimulus', $locked, $this->payload($request), $request->validated('revision_counter'));
        });

        return $request->expectsJson() ? response()->json(['revision_counter' => $revision->number]) : back()->with('status', 'Đã lưu bản nháp tài nguyên.');
    }

    private function payload(StimulusStoreRequest $request): array
    {
        $data = $request->safe()->except(['media', 'status', 'revision_counter', 'source', 'speaker_labels', 'exam_part_id', 'content_kind']);
        $root = $request->route('stimulus');
        if ($root instanceof LearningStimulus) {
            $current = app(ContentRevisionService::class)->payload('stimulus', $root);
            $data['metadata'] = array_replace($current['metadata'] ?? [], $data['metadata'] ?? []);
            $data['stimulus_type'] = $root->stimulus_type;
            $oldPart = (int) ($current['metadata']['exam_part_id'] ?? 0);
            if ($oldPart && (int) ($data['metadata']['exam_part_id'] ?? 0) !== $oldPart) {
                abort_if(app(QuestionBankCatalog::class)->entryFor('stimulus', $root->id)['questions']->isNotEmpty(), 422, 'Bài đã có câu hỏi. Giữ phần hiện tại để bảo toàn liên kết.');
                $data['metadata']['question_group_id'] = app(QuestionController::class)->createStimulusGroup($root, (int) $data['metadata']['exam_part_id']);
            }
        }
        if ($request->hasFile('media')) {
            $asset = app(MediaService::class)->store($request->file('media'), auth()->id());
            $data['metadata']['asset_id'] = $asset->id;
        }

        return $data;
    }

    private function standaloneQuestions(Request $request, array $data, Collection $entries, string $skill): LengthAwarePaginator
    {
        $listed = $entries->flatMap(fn ($entry) => $entry['questions']->keys())->unique()->flip();
        $search = mb_strtolower(trim($request->string('q')->toString()));
        $rows = collect($data['records'])->filter(function ($record, $id) use ($request, $listed, $skill, $search): bool {
            $part = $record['question']->examPart;

            return ! $listed->has($id) && $part?->skill?->code === $skill && $part?->examType?->code !== 'thpt'
                && (! $request->filled('status') || $record['status'] === $request->input('status'))
                && (! $request->filled('exam') || $part?->examType?->code === $request->input('exam'))
                && (! $request->integer('part') || $part?->id === $request->integer('part'))
                && ($search === '' || str_contains(mb_strtolower('Q-'.$id.' '.strip_tags($record['payload']['prompt'] ?? '')), $search));
        })->sortByDesc(fn ($record) => $record['question']->updated_at)->values();
        $page = max(1, $request->integer('standalone_page', 1));

        return new LengthAwarePaginator($rows->forPage($page, 15)->values(), $rows->count(), 15, $page, ['path' => $request->url(), 'pageName' => 'standalone_page', 'query' => $request->query()]);
    }

    /** @return array<int, int> */
    private function audioAssetIds(LearningStimulus $stimulus): array
    {
        $ids = [(int) ($stimulus->metadata['asset_id'] ?? 0)];

        foreach (ContentRevision::query()->where(['kind' => 'stimulus', 'entity_id' => $stimulus->id])->get(['payload']) as $revision) {
            $ids[] = (int) ($revision->payload['metadata']['asset_id'] ?? 0);
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function assetIsReferenced(int $assetId): bool
    {
        if (! MediaAsset::query()->whereKey($assetId)->exists()) {
            return false;
        }

        return
            LearningStimulus::query()->where('metadata->asset_id', $assetId)->exists()
            || ContentRevision::query()->whereIn('kind', ['stimulus', 'question'])->where(function ($query) use ($assetId): void {
                $query->where('payload->metadata->asset_id', $assetId)
                    ->orWhere('payload->metadata->audio_id', $assetId)
                    ->orWhere('payload->metadata->image_id', $assetId);
            })->exists()
            || DB::table('question_groups')->where('metadata->audio_id', $assetId)->exists()
            || DB::table('attempt_answers')->where('response->asset_id', $assetId)->exists();
    }
}
