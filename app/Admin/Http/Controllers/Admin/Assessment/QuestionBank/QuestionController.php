<?php

namespace VistiqAdmin\Http\Controllers\Admin\Assessment\QuestionBank;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;
use VistiqAdmin\Http\Controllers\StaticPageController;
use VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank\QuestionImportRequest;
use VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank\QuestionUpsertRequest;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\LearningItem;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\QuestionAcceptedAnswer;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Models\QuestionDifficulty;
use VistiqCore\Models\QuestionType;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Assessment\QuestionBank\MediaService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionAudioService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionBankCatalog;
use VistiqCore\Services\Assessment\QuestionBank\QuestionEditorService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionDeletionService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;
use VistiqCore\Services\Assessment\QuestionBank\QuestionWorkbookImportService;
use VistiqCore\Services\Assessment\QuestionBank\SubjectiveQuestionWorkbookService;
use VistiqCore\Services\Assessment\QuestionBank\ThptEnglishWorkbookImportService;
use VistiqCore\Services\System\SettingsService;
use VistiqCore\Services\System\AuditService;

class QuestionController extends StaticPageController
{
    /** @var array<string, string> */
    private const CSV_HEADER_ALIASES = [
        'question' => 'prompt',
        'content' => 'prompt',
        'part' => 'exam_part',
        'type' => 'question_type',
        'level' => 'difficulty',
        'answer' => 'correct_option',
        'media' => 'stimulus',
        'audio_group_id' => 'audio_group',
    ];

    /** @var array<string, string> */
    private const CSV_TEMPLATES = [
        'all' => 'vistiq-question-bank-template.csv',
        'toeic' => 'vistiq-toeic-template.csv',
        'ielts' => 'vistiq-ielts-template.csv',
        'listening-groups' => 'vistiq-listening-groups-template.csv',
        'readings' => 'vistiq-readings-with-questions-template.csv',
        'parts' => 'part-skill-guide.csv',
        'types' => 'question-type-guide.csv',
        'difficulty' => 'difficulty-guide.csv',
        'guide' => 'HUONG_DAN_CSV.md',
        'bundle' => 'vistiq-question-bank-templates.zip',
    ];

    /** @var array<string, array{title: string, frame: string, view: string, section: string, description: string}> */
    private const SCREENS = [
        'admin-questions' => ['title' => 'Ngân hàng câu hỏi', 'frame' => 'A02', 'view' => 'pages.admin.assessment.question-bank.questions.index', 'section' => 'admin', 'description' => 'Quản lý câu hỏi TOEIC, IELTS, THPT và các dạng bài luyện tập.'],
        'admin-question-edit' => ['title' => 'Tạo / chỉnh sửa câu hỏi', 'frame' => 'A03', 'view' => 'pages.admin.assessment.question-bank.questions.edit', 'section' => 'admin', 'description' => 'Tạo câu hỏi, đáp án, giải thích và liên kết media.'],
        'admin-question-deleted' => ['title' => 'Câu hỏi đã xóa', 'frame' => 'A02', 'view' => 'pages.admin.assessment.question-bank.questions.deleted', 'section' => 'admin', 'description' => 'Khôi phục câu hỏi đã xóa mềm khỏi ngân hàng.'],
    ];

    public function show(string $screen): View|RedirectResponse
    {
        return match ($screen) {
            'admin-questions' => $this->index(request()),
            'admin-question-edit' => $this->create(),
            default => $this->renderKnownScreen($screen, self::SCREENS),
        };
    }

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->routeIs('admin.questions.index') && ! $request->expectsJson()) {
            return redirect()->route($request->input('exam') === 'thpt' ? 'admin.questions.thpt' : ($request->input('reading') === 'yes' ? 'admin.readings.index' : 'admin.questions.listening'), $request->except('reading', 'audio'));
        }
        $search = trim($request->string('q')->toString());
        $exam = $request->string('exam')->toString();
        $part = $request->string('part')->toString();
        $isThptEnglish = $request->routeIs('admin.questions.thpt');
        $thptFamilyParts = [
            'short_cloze' => ['tieng-anh-part-1', 'tieng-anh-part-2'],
            'arrangement' => ['tieng-anh-part-3'],
            'extended_completion' => ['tieng-anh-part-4'],
            'reading' => ['tieng-anh-part-5', 'tieng-anh-part-6'],
        ];
        $family = $isThptEnglish && array_key_exists($request->string('family')->toString(), $thptFamilyParts)
            ? $request->string('family')->toString()
            : '';
        $type = $request->string('type')->toString();
        $difficulty = $request->string('difficulty')->toString();
        $status = $request->string('status')->toString();
        $missing = $request->boolean('missing');
        $audio = in_array($request->input('audio'), ['yes', 'no'], true) ? $request->input('audio') : '';
        $reading = in_array($request->input('reading'), ['yes', 'no'], true) ? $request->input('reading') : '';
        $sort = in_array($request->input('sort'), ['oldest', 'newest', 'id'], true) ? $request->input('sort') : 'newest';
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 10;
        $examTypes = DB::table('exam_types')->where('is_active', true)->orderBy('id')->get();
        $parts = ExamPart::with(['examType', 'skill'])
            ->whereIn('exam_type_id', $examTypes->pluck('id'))
            ->orderBy('exam_type_id')->orderBy('sort_order')->get();
        $questionTypes = QuestionType::orderBy('id')->get();
        $registry = app(QuestionRegistry::class);

        if ($exam !== '' && ! $examTypes->contains('code', $exam)) {
            $exam = '';
        }

        $availableParts = $parts->filter(fn (ExamPart $examPart): bool => ($exam === '' || $examPart->examType->code === $exam)
            && (! $isThptEnglish || str_starts_with($examPart->code, 'tieng-anh-part-')))->values();
        if ($part !== '' && ! $availableParts->contains('code', $part)) {
            $part = '';
        }

        $allowedTypeCodes = $availableParts
            ->filter(fn (ExamPart $examPart): bool => $part === '' || $examPart->code === $part)
            ->flatMap(fn (ExamPart $examPart): array => $registry->allowedTypes($examPart->examType->code, $examPart->skill->code))
            ->unique()->values()->all();
        $filterQuestionTypes = $questionTypes->whereIn('code', $allowedTypeCodes)->values();
        if ($type !== '' && ! $filterQuestionTypes->contains('code', $type)) {
            $type = '';
        }

        $filterCatalog = [
            'parts' => $availableParts->map(fn (ExamPart $examPart): array => [
                'code' => $examPart->code,
                'exam' => $examPart->examType->code,
                'name' => $examPart->examType->name.' · '.$examPart->name,
                'types' => $registry->allowedTypes($examPart->examType->code, $examPart->skill->code),
            ])->all(),
            'types' => $questionTypes->map(fn (QuestionType $questionType): array => [
                'code' => $questionType->code, 'name' => $questionType->name,
            ])->all(),
        ];

        $catalog = app(QuestionBankCatalog::class);
        $bankData = $catalog->catalog();
        $matching = $catalog->matching(compact('search', 'exam', 'part', 'type', 'difficulty', 'status', 'missing', 'audio', 'reading'));
        if ($isThptEnglish) {
            $englishPartIds = $availableParts->pluck('id')->map(fn ($id) => (int) $id)->all();
            $familyCodes = $thptFamilyParts[$family] ?? null;
            $matching = $matching->filter(function (QuestionBankItem $question) use ($englishPartIds, $familyCodes): bool {
                $partCode = $question->examPart?->code;

                return in_array((int) $question->exam_part_id, $englishPartIds, true)
                    && ($familyCodes === null || in_array($partCode, $familyCodes, true));
            })->values();
        }
        $matching = $matching->sortBy(fn ($q) => $sort === 'id' ? $q->id : $q->updated_at, SORT_REGULAR, $sort !== 'oldest')->values();
        $matchingQuestionIds = $matching->pluck('id')->all();
        $thptStats = null;
        $thptGroups = collect();
        $thptGroupsById = collect();
        $thptFamilyCounts = [];
        if ($isThptEnglish) {
            $allThptQuestions = $catalog->matching([
                'search' => '', 'exam' => 'thpt', 'part' => '', 'type' => '', 'difficulty' => '',
                'status' => '', 'missing' => false, 'audio' => '', 'reading' => '',
            ])->filter(fn (QuestionBankItem $question): bool => in_array((int) $question->exam_part_id, $englishPartIds, true))->values();
            $thptRecords = collect($bankData['records'])->only($allThptQuestions->pluck('id')->all());
            $thptFamilyCounts = collect($thptFamilyParts)->map(fn (array $codes): int => $allThptQuestions->filter(
                fn (QuestionBankItem $question): bool => in_array($question->examPart?->code, $codes, true),
            )->count())->all();
            $thptStats = [
                'total' => $allThptQuestions->count(),
                'groups' => $thptRecords->pluck('payload.group_id')->filter()->unique()->count(),
                'short_cloze' => $thptFamilyCounts['short_cloze'] ?? 0,
                'arrangement' => $thptFamilyCounts['arrangement'] ?? 0,
                'extended_completion' => $thptFamilyCounts['extended_completion'] ?? 0,
                'reading' => $thptFamilyCounts['reading'] ?? 0,
                'approved' => $thptRecords->whereIn('status', ['approved', 'published'])->count(),
                'pending' => $thptRecords->whereIn('status', ['draft', 'review'])->count(),
            ];
            $thptGroups = DB::table('question_groups')
                ->join('exam_parts', 'exam_parts.id', '=', 'question_groups.exam_part_id')
                ->whereIn('question_groups.exam_part_id', $availableParts->pluck('id'))
                ->orderBy('question_groups.title')
                ->select('question_groups.id', 'question_groups.title', 'question_groups.exam_part_id', 'exam_parts.code as part_code', 'exam_parts.name as part_name')
                ->get();
            $thptGroupsById = $thptGroups->keyBy('id');
        }
        $page = max(1, $request->integer('page', 1));
        $bankRoute = $request->routeIs('admin.questions.thpt') ? 'admin.questions.thpt' : 'admin.questions.index';
        $questions = new LengthAwarePaginator($matching->forPage($page, $perPage)->values(), $matching->count(), $perPage, $page, ['path' => route($bankRoute), 'query' => $request->query()]);
        $entryRows = $bankData['entries']->filter(fn ($e) => $e['questions']->keys()->intersect($matchingQuestionIds)->isNotEmpty())->map(function ($e) use ($matchingQuestionIds) {
            $e['matching_ids'] = $e['questions']->keys()->intersect($matchingQuestionIds)->values()->all();

            return $e;
        })->sortBy(fn ($e) => $sort === 'id' ? $e['id'] : $e['updated_at'], SORT_REGULAR, $sort !== 'oldest')->values();
        $entries = new LengthAwarePaginator($entryRows->forPage($page, $perPage)->values(), $entryRows->count(), $perPage, $page, ['path' => route($bankRoute), 'query' => $request->query()]);
        $missingResourcesByQuestion = collect($bankData['records'])->mapWithKeys(fn ($r, $id) => [$id => $r['missing']]);
        $previewPayloads = collect($bankData['records'])->mapWithKeys(fn ($r, $id) => [$id => $r['payload']]);
        $previewAssets = $bankData['assets'];
        $questionTags = DB::table('learning_item_tags')->join('tags', 'tags.id', '=', 'learning_item_tags.tag_id')->whereIn('learning_item_id', $bankData['questions']->pluck('learning_item_id'))->select('learning_item_id', 'tags.name')->get()->groupBy('learning_item_id');

        return $this->renderScreen('admin-questions', self::SCREENS['admin-questions'])->with([
            'questions' => $questions,
            'entries' => $entries,
            'bankData' => $bankData,
            'matchingQuestionIds' => $matchingQuestionIds,
            'missingResourcesByQuestion' => $missingResourcesByQuestion,
            'filters' => compact('search', 'exam', 'part', 'type', 'difficulty', 'status', 'missing', 'audio', 'reading', 'sort', 'perPage', 'family'),
            'isThptEnglish' => $isThptEnglish,
            'thptFamilies' => [
                'short_cloze' => 'Điền từ ngắn',
                'arrangement' => 'Sắp xếp câu',
                'extended_completion' => 'Điền cụm / câu',
                'reading' => 'Đọc hiểu',
            ],
            'thptStats' => $thptStats,
            'thptFamilyCounts' => $thptFamilyCounts,
            'thptGroups' => $thptGroups,
            'thptGroupsById' => $thptGroupsById,
            'questionTags' => $questionTags,
            'previewPayloads' => $previewPayloads,
            'previewAssets' => $previewAssets,
            'examTypes' => $examTypes,
            'examCounts' => $examTypes->mapWithKeys(fn (object $examType): array => [
                $examType->code => QuestionBankItem::whereHas('examPart', fn ($parts) => $parts->where('exam_type_id', $examType->id))->count(),
            ]),
            'parts' => $availableParts,
            'questionTypes' => $questionTypes,
            'filterQuestionTypes' => $filterQuestionTypes,
            'filterCatalog' => $filterCatalog,
            'csvGuide' => json_decode(file_get_contents(resource_path('import-templates/questions/catalog-guide.json')), true, 512, JSON_THROW_ON_ERROR),
            'difficulties' => QuestionDifficulty::orderBy('sort_order')->get(),
            'stats' => [
                'total' => QuestionBankItem::count(),
                'published' => QuestionBankItem::whereHas('learningItem', fn ($items) => $items->where('status', 'published'))->count(),
                'draft' => QuestionBankItem::where(fn ($query) => $query->whereHas('draftRevision', fn ($revision) => $revision->where('status', 'draft'))->orWhere(fn ($query) => $query->whereNull('draft_revision_id')->whereHas('learningItem', fn ($items) => $items->where('status', 'draft'))))->count(),
                'readings' => collect($bankData['records'])->where('reading', true)->count(),
                'audio' => collect($bankData['records'])->where('audio', true)->count(),
            ],
        ]);
    }

    public function thpt(Request $request): View
    {
        $request->merge(['exam' => 'thpt']);

        return $this->index($request);
    }

    public function deleted(): View
    {
        Gate::authorize('content.view');
        $questions = QuestionBankItem::onlyTrashed()
            ->with(['examPart.examType', 'questionType'])
            ->orderByDesc('deleted_at')
            ->paginate(25);

        return $this->renderScreen('admin-question-deleted', self::SCREENS['admin-question-deleted'])->with(compact('questions'));
    }

    public function destroy(QuestionBankItem $question, QuestionDeletionService $deletions): RedirectResponse
    {
        $result = $deletions->delete((int) $question->id);
        $message = $result === 'disabled'
            ? 'Câu hỏi thuộc đề đã phát hành đã ngừng sử dụng và vẫn được giữ trong đề.'
            : 'Đã xóa mềm câu hỏi Q-'.$question->id.'. Lịch sử làm bài và dữ liệu xem lại được giữ nguyên.';

        return back()->with('status', $message);
    }

    public function restore(int $question, AuditService $audit): RedirectResponse
    {
        Gate::authorize('content.edit');
        $restored = DB::transaction(function () use ($question, $audit): QuestionBankItem {
            $item = QuestionBankItem::withTrashed()->lockForUpdate()->findOrFail($question);
            abort_unless($item->trashed(), 404);
            $before = ['deleted_at' => $item->deleted_at?->toIso8601String()];
            $item->restore();
            $audit->record('question.restore', 'question', $item->id, $before, ['soft_deleted' => false]);

            return $item;
        });

        return redirect()->route('admin.questions.edit', $restored)->with('status', 'Đã khôi phục câu hỏi Q-'.$restored->id.' trong ngân hàng. Câu chưa được tự động thêm lại vào đề nào.');
    }

    public function bankPreview(Request $request, string $kind, int $id): mixed
    {
        abort_unless(in_array($kind, ['stimulus', 'media', 'group', 'question'], true), 404);
        $catalog = app(QuestionBankCatalog::class);
        $bankData = $catalog->catalog();
        $entry = $catalog->entryFor($kind, $id);
        $matching = $catalog->matching($request->only('exam', 'part', 'type', 'difficulty', 'status', 'audio', 'reading') + ['search' => $request->string('q')->toString(), 'missing' => $request->boolean('missing')]);
        if ($request->boolean('thpt_english')) {
            $familyParts = [
                'short_cloze' => ['tieng-anh-part-1', 'tieng-anh-part-2'],
                'arrangement' => ['tieng-anh-part-3'],
                'extended_completion' => ['tieng-anh-part-4'],
                'reading' => ['tieng-anh-part-5', 'tieng-anh-part-6'],
            ];
            $familyCodes = $familyParts[$request->string('family')->toString()] ?? null;
            $matching = $matching->filter(function (QuestionBankItem $question) use ($familyCodes): bool {
                $code = $question->examPart?->code ?? '';

                return str_starts_with($code, 'tieng-anh-part-')
                    && ($familyCodes === null || in_array($code, $familyCodes, true));
            })->values();
        }
        $entry['matching_ids'] = $matching->pluck('id')->all();

        return view('pages.admin.assessment.question-bank.questions.bank-entry-preview', compact('entry', 'bankData'));
    }

    private function embeddedAttributes(QuestionUpsertRequest $request, ?QuestionBankItem $question = null): array
    {
        $attributes = $request->validated();
        if (! $request->filled('editor_stimulus_id')) {
            return $attributes;
        }
        $stimulus = LearningStimulus::query()->lockForUpdate()->findOrFail($request->integer('editor_stimulus_id'));
        $payload = app(ContentRevisionService::class)->payload('stimulus', $stimulus);
        $partId = (int) ($payload['metadata']['exam_part_id'] ?? 0);
        $questionPart = ExamPart::with('skill')->findOrFail($attributes['exam_part_id']);
        abort_if($stimulus->stimulus_type === 'reading' && $questionPart->skill?->code !== 'reading', 422, 'Chọn phần đọc hiểu cho câu hỏi của bài.');
        if ($partId) {
            abort_unless((int) $attributes['exam_part_id'] === $partId, 422, 'Câu hỏi phải thuộc phần đã chọn của bài.');
        }
        if ($question) {
            $entry = app(QuestionBankCatalog::class)->entryFor('stimulus', $stimulus->id);
            abort_unless($entry['questions']->has($question->id), 422, 'Câu hỏi không thuộc bài đang mở.');
            $current = app(ContentRevisionService::class)->payload('question', $question);
            $attributes['question_group_id'] = $current['group_id'] ?? null;
            $attributes['group_order'] = $current['group_order'] ?? 0;
            $attributes['stimulus_ids'] = array_values(array_unique(array_map('intval', array_merge($current['stimulus_ids'] ?? [], [$stimulus->id]))));
        } else {
            $groupId = (int) ($payload['metadata']['question_group_id'] ?? 0);
            $group = DB::table('question_groups')->where('id', $groupId)->first();
            if (! $group || (int) $group->exam_part_id !== (int) $attributes['exam_part_id']) {
                $groupId = (int) DB::table('question_groups')->where('exam_part_id', $attributes['exam_part_id'])->where(function ($query) use ($stimulus) {
                    $query->whereJsonContains('stimulus_ids', $stimulus->id)->orWhereJsonContains('stimulus_ids', (string) $stimulus->id);
                })->value('id');
                if (! $groupId) {
                    $groupId = $this->createStimulusGroup($stimulus, (int) $attributes['exam_part_id']);
                }
            }
            $attributes['question_group_id'] = $groupId;
            $groupQuestions = app(QuestionBankCatalog::class)->catalog()['records'];
            $orders = collect($groupQuestions)->filter(fn ($r) => (int) ($r['payload']['group_id'] ?? 0) === $groupId)->map(fn ($r) => (int) ($r['payload']['group_order'] ?? 0));
            $attributes['group_order'] = $orders->isEmpty() ? 0 : $orders->max() + 1;
            abort_if($attributes['group_order'] > 200, 422, 'Nhóm đã đạt giới hạn thứ tự câu hỏi.');
        }
        $attributes['learning_stimulus_id'] = $stimulus->id;

        return $attributes;
    }

    public function createStimulusGroup(LearningStimulus $stimulus, int $partId): int
    {
        $part = ExamPart::with(['examType', 'skill'])->findOrFail($partId);
        $kind = match ($part->code) {
            'part-3' => 'conversation', 'part-4' => 'talk', 'part-6' => 'part6', default => $part->examType->code === 'ielts' ? 'ielts_section' : 'reading_set'
        };
        $id = DB::table('question_groups')->insertGetId(['title' => $stimulus->title, 'kind' => $kind, 'exam_part_id' => $partId, 'stimulus_ids' => json_encode([$stimulus->id]), 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);

        return (int) $id;
    }

    private function savedResponse(QuestionBankItem $question, Request $request): mixed
    {
        $question->refresh();
        $result = ['id' => $question->id, 'revision_counter' => $question->revision_counter, 'updated_at' => now()->format('d/m/Y H:i'), 'status' => 'draft', 'workflow_html' => view('shared.workflow-actions', ['kind' => 'question', 'entityId' => $question->id, 'revision' => ContentRevision::find($question->draft_revision_id)])->render()];
        if ($request->filled('editor_stimulus_id')) {
            $catalog = app(QuestionBankCatalog::class);
            $result['questions_html'] = view('pages.admin.assessment.question-bank.workspace.reading-questions', ['entry' => $catalog->entryFor('stimulus', $request->integer('editor_stimulus_id')), 'bankData' => $catalog->catalog()])->render();
        }

        return response()->json($result);
    }

    public function create(Request $request): View
    {
        $data = $this->editorData();
        $thptContext = $request->query('from') === 'thpt';
        $partQuery = ExamPart::query();
        if ($thptContext) {
            $partQuery->whereHas('examType', fn ($query) => $query->where('code', 'thpt'))
                ->where('code', 'like', 'tieng-anh-part-%');
        }
        $part = $request->filled('exam_part_id')
            ? (clone $partQuery)->whereKey($request->integer('exam_part_id'))->first()
            : null;
        $part ??= $thptContext ? (clone $partQuery)->where('code', 'tieng-anh-part-1')->first() : null;
        if ($part) {
            $data['payload']['exam_part_id'] = $part->id;
            $data['editorPreview'] = $this->previewData($data['payload']);
        }

        return $this->renderScreen('admin-question-edit', self::SCREENS['admin-question-edit'])->with($data);
    }

    public function store(QuestionUpsertRequest $request): mixed
    {
        $question = DB::transaction(function () use ($request) {
            $attributes = $this->embeddedAttributes($request);
            if (filled($attributes['question_group_id'] ?? null) && ! array_key_exists('group_order', $attributes)) {
                $orders = collect(app(QuestionBankCatalog::class)->catalog()['records'])
                    ->filter(fn (array $record): bool => (int) ($record['payload']['group_id'] ?? 0) === (int) $attributes['question_group_id'])
                    ->map(fn (array $record): int => (int) ($record['payload']['group_order'] ?? 0));
                $attributes['group_order'] = $orders->isEmpty() ? 0 : $orders->max() + 1;
            }

            return $this->persistQuestion($attributes);
        });
        if ($request->expectsJson()) {
            return $this->savedResponse($question, $request);
        }
        if ($request->boolean('return_to_thpt_bank')) {
            return redirect()->route('admin.questions.thpt')->with('status', 'Đã tạo câu hỏi mới.');
        }

        return redirect()->route('admin.questions.edit', array_filter(['question' => $question->id, 'from' => $request->input('return_to') === 'thpt' ? 'thpt' : null]))->with('status', 'Đã tạo câu hỏi mới.');
    }

    public function import(QuestionImportRequest $request, MediaService $media): mixed
    {
        set_time_limit(0);
        $returnRoute = match ($request->input('return_to')) {
            'readings' => 'admin.readings.index',
            'thpt' => 'admin.questions.thpt',
            default => 'admin.readings.index',
        };
        $importToken = $request->string('import_key')->toString();
        $progressKey = $this->importProgressKey($request->user()->id, $importToken);
        $offset = (int) $request->input('offset', 0);
        $state = Cache::get($progressKey);
        $fileHash = hash_file('sha256', $request->file('file')->getRealPath());

        if ($offset === 0) {
            $state = ['status' => 'processing', 'processed' => 0, 'total' => 0, 'imported' => 0, 'errors' => [], 'file_hash' => $fileHash, 'audio_groups' => [], 'readings' => [], 'thpt_image_assets' => []];
        } else {
            abort_unless(is_array($state)
                && $state['status'] === 'processing'
                && $state['processed'] === $offset
                && isset($state['file_hash'])
                && hash_equals($state['file_hash'], $fileHash), 409, 'Tiến trình import đã hết hạn hoặc file đã thay đổi. Hãy bắt đầu lại.');
        }

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return $this->csvImportError($request, $returnRoute, 'Không thể đọc file CSV.');
        }

        $firstLine = fgets($handle);

        if ($firstLine === false) {
            fclose($handle);

            return $this->csvImportError($request, $returnRoute, 'File CSV không có dữ liệu.');
        }

        $delimiter = $this->detectCsvDelimiter($firstLine);
        $headers = array_map(fn ($header): string => $this->normalizeCsvHeader((string) $header), str_getcsv($firstLine, $delimiter, '"', ''));

        if ($this->hasInvalidCsvHeaders($headers)) {
            fclose($handle);

            return $this->csvImportError($request, $returnRoute, 'CSV phải có hàng tiêu đề hợp lệ và không được trùng tên cột.');
        }

        $missingHeaders = array_values(array_diff(['exam_part', 'prompt'], $headers));
        if ($request->input('return_to') === 'readings') {
            $missingHeaders = array_merge($missingHeaders, array_diff(['reading_key', 'reading_title', 'reading_body'], $headers));
        }

        if ($missingHeaders !== []) {
            fclose($handle);

            return $this->csvImportError($request, $returnRoute, 'CSV thiếu cột bắt buộc: '.implode(', ', $missingHeaders).'.');
        }

        $parts = ExamPart::with(['examType', 'skill'])->get();
        $questionTypes = QuestionType::query()->get(['id', 'code']);
        $difficulties = QuestionDifficulty::query()->get(['id', 'code']);
        $stimuli = LearningStimulus::query()->get(['id', 'title', 'stimulus_type']);
        $partMap = $this->catalogMap($parts, 'code');
        $questionTypeMap = $this->catalogMap($questionTypes, 'code');
        $difficultyMap = $this->catalogMap($difficulties, 'code');
        $stimulusMap = $this->catalogMap($stimuli, 'title');
        $uploadedThptImages = [];
        $imageFiles = $request->file('thpt_images', []);
        foreach ($request->input('thpt_image_refs', []) as $index => $reference) {
            if (! is_array($reference) || ! in_array($reference['exam_part'] ?? null, ['tieng-anh-part-1', 'tieng-anh-part-2'], true)) {
                continue;
            }
            $readingKey = trim((string) ($reference['reading_key'] ?? ''));
            $image = $imageFiles[$index] ?? null;
            if ($readingKey !== '' && $image instanceof UploadedFile) {
                $uploadedThptImages[Str::lower($reference['exam_part'].'|'.$readingKey)] = $image;
            }
        }
        $state['thpt_image_assets'] ??= [];
        $audioGroups = $state['audio_groups'] ?? [];
        $readings = $state['readings'] ?? [];
        $defaultQuestionType = (int) $request->validated('default_question_type');
        $defaultDifficulty = (int) $request->validated('default_difficulty');
        $total = $this->countCsvRows($handle, $delimiter);
        abort_unless($offset <= $total && ($offset === 0 || $state['total'] === $total), 409, 'Không thể tiếp tục import tại vị trí này.');
        $state['total'] = $total;
        Cache::put($progressKey, $state, now()->addMinutes(20));
        $imported = $state['imported'];
        $processed = $offset;
        $errors = $state['errors'];
        $rowNumber = 1;
        $skippedRows = 0;

        while ($skippedRows < $offset && ($skippedFields = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rowNumber++;
            if (! $this->isBlankCsvRow($skippedFields)) {
                $skippedRows++;
            }
        }

        abort_unless($skippedRows === $offset, 409, 'Không thể tiếp tục import tại vị trí này.');
        $batchProcessed = 0;
        $batchSize = $request->expectsJson() ? 50 : 2000;

        while ($batchProcessed < $batchSize && ($fields = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rowNumber++;

            if ($this->isBlankCsvRow($fields)) {
                continue;
            }

            $processed++;
            $batchProcessed++;

            if ($processed > 2000) {
                $errors[] = 'Đã dừng ở dòng '.$rowNumber.' vì mỗi lần import tối đa 2.000 câu hỏi.';
                break;
            }

            if (count($fields) > count($headers)) {
                $errors[] = 'Dòng '.$rowNumber.': Số ô vượt số cột; kiểm tra dấu phẩy và dấu nháy kép trong nội dung.';

                continue;
            }

            $values = array_pad(array_slice($fields, 0, count($headers)), count($headers), '');
            $row = array_combine($headers, $values);
            $attributes = $this->attributesFromCsvRow(
                $row,
                $partMap,
                $questionTypeMap,
                $difficultyMap,
                $stimulusMap,
                $defaultQuestionType,
                $defaultDifficulty,
            );
            if (filled($row['payload_json'] ?? null)) {
                $payload = json_decode($row['payload_json'], true);
                if (! is_array($payload) || ! str_starts_with(ltrim($row['payload_json']), '{')) {
                    $errors[] = 'Dòng '.$rowNumber.': payload_json phải là JSON object hợp lệ.';

                    continue;
                }
                $attributes = array_replace($attributes, array_intersect_key($payload, array_flip(['prompt', 'explanation', 'options', 'correct_options', 'accepted_answers', 'metadata'])));
                $attributes['learning_stimulus_id'] = $payload['stimulus_ids'][0] ?? $attributes['learning_stimulus_id'];
                $attributes['question_group_id'] = $payload['group_id'] ?? null;
            }
            $audioGroupName = trim((string) ($row['audio_group'] ?? ''));
            $audioPart = $parts->firstWhere('id', $attributes['exam_part_id']);

            if ($audioPart?->skill?->code === 'listening') {
                $attributes['learning_stimulus_id'] = null;
                if (is_array($attributes['metadata'] ?? null)) {
                    unset($attributes['metadata']['audio_id']);
                }
            }

            if ($audioGroupName === ''
                && ($attributes['skill'] === 'listening' || $audioPart?->skill?->code === 'listening')
                && preg_match('/Audio script ID:\s*([A-Za-z0-9][A-Za-z0-9_-]*)/i', (string) ($row['sample_note'] ?? ''), $audioGroupMatch) === 1) {
                $audioGroupName = $audioGroupMatch[1];
            }

            if ($audioGroupName !== '' && filled($attributes['question_group_id'] ?? null)) {
                $errors[] = 'Dòng '.$rowNumber.': Dùng audio_group hoặc payload_json.group_id, không điền cả hai.';

                continue;
            }

            $audioGroupKey = Str::lower(trim($audioGroupName));
            $attributes['audio_group'] = $audioGroupName;

            if ($audioGroupKey !== '' && isset($audioGroups[$audioGroupKey])) {
                $attributes['question_group_id'] = $audioGroups[$audioGroupKey]['id'];
                $attributes['group_order'] = $audioGroups[$audioGroupKey]['next_order'];
            }

            $validator = Validator::make($attributes, [
                'exam_type' => ['nullable', 'string', 'in:toeic,ielts,thpt'],
                'skill' => ['nullable', 'string', 'in:listening,reading,writing,speaking,thpt'],
                'exam_part_id' => ['required', 'integer', 'exists:exam_parts,id'],
                'question_type_id' => ['required', 'integer', 'exists:question_types,id'],
                'question_difficulty_id' => ['required', 'integer', 'exists:question_difficulties,id'],
                'learning_stimulus_id' => ['nullable', 'integer', 'exists:learning_stimuli,id'],
                'audio_group' => ['nullable', 'string', 'max:255'],
                'prompt' => ['required', 'string', 'max:10000'],
                'explanation' => ['nullable', 'string', 'max:10000'],
                'status' => ['required', 'string', 'in:draft'],
                'options' => ['nullable', 'array', 'max:30'],
                'options.*' => ['nullable', 'string', 'max:2000'],
                'metadata' => ['nullable', 'array'],
                'correct_options' => ['nullable', 'array', 'max:30'],
                'correct_options.*' => ['string', 'max:12'],
                'question_group_id' => ['nullable', 'integer', 'exists:question_groups,id'],
                'group_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
                'correct_option' => ['nullable', 'string', 'max:12'],
                'accepted_answers' => ['nullable', 'array', 'max:20'],
                'accepted_answers.*' => ['nullable', 'string', 'max:500'],
                'tags' => ['nullable', 'string', 'max:500'],
            ], [
                'exam_type.in' => 'exam_type chỉ dùng toeic, ielts hoặc thpt.',
                'skill.in' => 'skill không hợp lệ với danh mục kỳ thi.',
                'exam_part_id.required' => 'exam_part không hợp lệ; dùng đúng mã Part/Kỹ năng trong hướng dẫn.',
                'question_type_id.required' => 'question_type không hợp lệ; dùng đúng mã loại câu hỏi trong hướng dẫn.',
                'question_difficulty_id.required' => 'difficulty không hợp lệ; dùng easy, medium hoặc hard.',
            ]);

            if ($validator->fails()) {
                $errors[] = 'Dòng '.$rowNumber.': '.$validator->errors()->first();

                continue;
            }

            try {
                $this->validateCsvClassification($attributes, $row, $parts, $questionTypes);
                $csvPart = $parts->firstWhere('id', $attributes['exam_part_id']);
                $isThptShortCloze = $csvPart?->examType?->code === 'thpt'
                    && in_array($csvPart->code, ['tieng-anh-part-1', 'tieng-anh-part-2'], true);
                $hasReading = $request->input('return_to') === 'readings'
                    || $isThptShortCloze
                    || filled($row['reading_key'] ?? null) || filled($row['reading_title'] ?? null) || filled($row['reading_body'] ?? null);
                if ($isThptShortCloze) {
                    $readingKey = trim((string) ($row['reading_key'] ?? ''));
                    if ($readingKey === '') {
                        throw ValidationException::withMessages(['reading_key' => 'Câu THPT Phần 1–2 cần reading_key để gom đúng văn bản dùng chung.']);
                    }
                    $imageKey = Str::lower($csvPart->code.'|'.$readingKey);
                    $imageId = (int) ($state['thpt_image_assets'][$imageKey] ?? 0);
                    if (! $imageId) {
                        $imageFile = $uploadedThptImages[$imageKey] ?? null;
                        if (! $imageFile instanceof UploadedFile || ! $imageFile->isValid()) {
                            throw ValidationException::withMessages(['thpt_images' => 'Đính kèm ảnh cho '.$csvPart->name.' · '.$readingKey.'.']);
                        }
                        $maximumBytes = ((int) (app(SettingsService::class)->get('media')['max_megabytes'] ?? 50)) * 1024 * 1024;
                        if ($imageFile->getSize() > $maximumBytes) {
                            throw ValidationException::withMessages(['thpt_images' => 'Ảnh '.$readingKey.' vượt giới hạn dung lượng của Tài nguyên.']);
                        }
                        $imageId = (int) $media->store($imageFile, $request->user()->id)->id;
                        $state['thpt_image_assets'][$imageKey] = $imageId;
                    }
                    $attributes['metadata'] = array_replace(is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [], ['image_id' => $imageId]);
                }
                if ($hasReading) {
                    $reading = $this->persistCsvReadingQuestion($attributes, $row, $readings);
                    $readings[$this->csvReadingStateKey((int) $attributes['exam_part_id'], (string) ($row['reading_key'] ?? ''))] = $reading;
                } else {
                    $persisted = $this->persistCsvQuestion($attributes, $audioGroupName, $audioGroups);
                    if ($audioGroupKey !== '') {
                        $audioGroups[$audioGroupKey] = $persisted['audio_group'];
                    }
                }

                $imported++;
            } catch (ValidationException $exception) {
                $errors[] = 'Dòng '.$rowNumber.': '.collect($exception->errors())->flatten()->first();
            } catch (Throwable $exception) {
                report($exception);
                $errors[] = 'Dòng '.$rowNumber.': Không thể lưu câu hỏi này.';
            }
        }

        fclose($handle);

        if ($processed < $total && $processed < 2000) {
            $state = ['status' => 'processing', 'processed' => $processed, 'total' => $total, 'imported' => $imported, 'errors' => $errors, 'file_hash' => $fileHash, 'audio_groups' => $audioGroups, 'readings' => $readings, 'thpt_image_assets' => $state['thpt_image_assets']];
            Cache::put($progressKey, $state, now()->addMinutes(20));

            if ($request->expectsJson()) {
                return response()->json(['status' => 'processing', 'processed' => $processed, 'total' => $total, 'next_offset' => $processed]);
            }
        }

        if ($processed === 2000 && $processed < $total) {
            $errors[] = 'Đã dừng vì mỗi lần import tối đa 2.000 câu hỏi.';
        }

        Cache::put($progressKey, ['status' => 'completed', 'processed' => $processed, 'total' => $total, 'imported' => $imported, 'failed' => count($errors)], now()->addMinutes(20));

        if ($processed === 0) {
            $errors[] = 'File CSV chưa có dòng dữ liệu nào.';
        }

        $summary = ['total' => $processed, 'imported' => $imported, 'failed' => count($errors)];
        if ($request->expectsJson()) {
            $request->session()->flash('import_summary', $summary);
            $request->session()->flash('import_errors', $errors);

            return response()->json(['summary' => $summary, 'errors' => $errors, 'redirect' => route($returnRoute)]);
        }

        return redirect()->route($returnRoute)->with('import_summary', $summary)->with('import_errors', $errors);
    }

    private function csvImportError(Request $request, string $returnRoute, string $message): mixed
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'errors' => ['file' => [$message]]], 422)
            : redirect()->route($returnRoute)->withErrors(['file' => $message])->withInput($request->except('file'));
    }

    public function importProgress(Request $request, string $token): mixed
    {
        abort_unless(Str::isUuid($token), 404);
        $state = Cache::get($this->importProgressKey($request->user()->id, $token), []);

        return response()->json([
            'status' => $state['status'] ?? 'waiting',
            'processed' => $state['processed'] ?? 0,
            'total' => $state['total'] ?? 0,
            'imported' => $state['imported'] ?? 0,
            'failed' => $state['failed'] ?? count($state['errors'] ?? []),
        ]);
    }

    public function previewWorkbook(Request $request, QuestionWorkbookImportService $workbooks, ThptEnglishWorkbookImportService $thptWorkbooks): mixed
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'kind' => ['required', 'in:listening,reading,writing,speaking,thpt_english'],
        ]);
        $path = $request->file('file')->getRealPath();
        $preview = match ($validated['kind']) {
            'thpt_english' => $thptWorkbooks->preview($path),
            'writing', 'speaking' => app(SubjectiveQuestionWorkbookService::class)->preview($path, $validated['kind']),
            default => $workbooks->preview($path, $validated['kind']),
        };
        $this->pruneExpiredWorkbookAudio((int) $request->user()->id);
        $token = (string) Str::uuid();
        Cache::put('question-workbook:'.$request->user()->id.':'.$token, $preview, now()->addMinutes(30));

        return response()->json([
            'token' => $token,
            'kind' => $preview['kind'],
            'total_questions' => $preview['total_questions'],
            'can_import' => $preview['can_import'] ?? true,
            'max_audio_megabytes' => $preview['kind'] === 'listening'
                ? (int) (app(SettingsService::class)->get('media')['max_megabytes'] ?? 50)
                : null,
            'max_audio_file_bytes' => $preview['kind'] === 'listening' ? $this->workbookAudioUploadLimitBytes() : null,
            'sheets' => collect($preview['sheets'])->map(fn (array $sheet): array => [
                'code' => $sheet['code'],
                'source_sheet' => $sheet['source_sheet'] ?? null,
                'title' => $preview['kind'] === 'listening'
                    ? 'Sheet '.$sheet['source_sheet'].' → '.$sheet['code']
                    : ($sheet['title'] ?? null),
                'question_count' => $sheet['question_count'],
                'expected_count' => $sheet['expected_count'] ?? null,
                'errors' => $sheet['errors'],
                'blocked' => $sheet['blocked'],
                'exam_type' => $sheet['exam_type'],
                'skill' => $sheet['skill'],
                'exam_part' => $sheet['exam_part'],
                'reading_title' => $preview['kind'] === 'reading' ? $sheet['reading_title'] : null,
                'reading_body' => $preview['kind'] === 'reading' ? $sheet['reading_body'] : null,
                'requires_question_images' => $preview['kind'] === 'listening'
                    && $sheet['exam_type'] === 'toeic'
                    && $sheet['skill'] === 'listening'
                    && $sheet['exam_part'] === 'part-1',
                'questions' => in_array($preview['kind'], ['writing', 'speaking'], true)
                    ? collect($sheet['questions'])->map(fn (array $question): array => [
                        'row' => $question['row'], 'prompt' => strip_tags($question['attributes']['prompt']),
                        'part_name' => $question['part_name'], 'min_words' => $question['attributes']['metadata']['min_words'] ?? null,
                    ])->values()
                    : ($preview['kind'] === 'listening'
                    && $sheet['exam_type'] === 'toeic'
                    && $sheet['skill'] === 'listening'
                    && $sheet['exam_part'] === 'part-1'
                    ? collect($sheet['questions'])->map(fn (array $question): array => [
                        'row' => $question['row'],
                        'prompt' => $question['attributes']['prompt'],
                    ])->values()
                    : []),
                'requires_group_image' => $sheet['requires_group_image'] ?? false,
            ])->values(),
        ]);
    }

    public function stageWorkbookAudio(Request $request): mixed
    {
        $validated = $request->validate([
            'token' => ['required', 'uuid'],
            'code' => ['required', 'string', 'max:120'],
            'audio' => ['required', 'file'],
        ]);
        $userId = (int) $request->user()->id;
        $cacheKey = 'question-workbook:'.$userId.':'.$validated['token'];
        $preview = Cache::get($cacheKey);
        abort_unless(is_array($preview) && ($preview['kind'] ?? null) === 'listening', 404, 'Bản xem trước Listening đã hết hạn. Hãy tải workbook lên lại.');

        $sheet = collect($preview['sheets'] ?? [])->first(fn (array $candidate): bool => ($candidate['code'] ?? null) === $validated['code'] && ! ($candidate['blocked'] ?? true) && ($candidate['questions'] ?? []) !== []);
        if (! $sheet) {
            return response()->json(['message' => 'Không tìm thấy bài Listening cần nhận audio.'], 422);
        }

        $file = $request->file('audio');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return response()->json(['message' => 'Máy chủ không nhận được file audio hợp lệ. Kiểm tra giới hạn tải lên của PHP rồi thử lại.'], 422);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());
        $validMime = str_starts_with($mime, 'audio/') || ($extension === 'm4a' && $mime === 'video/mp4');
        if (! in_array($extension, ['mp3', 'wav', 'm4a', 'ogg'], true) || ! $validMime) {
            return response()->json(['message' => 'File audio phải là MP3, WAV, M4A hoặc OGG.'], 422);
        }

        $maximumBytes = min(
            ((int) (app(SettingsService::class)->get('media')['max_megabytes'] ?? 50)) * 1024 * 1024,
            $this->workbookAudioUploadLimitBytes(),
        );
        if ($file->getSize() > $maximumBytes) {
            return response()->json(['message' => 'File '.$file->getClientOriginalName().' vượt giới hạn tải lên hiện tại ('.number_format($maximumBytes / 1024 / 1024, 1).' MB/file).'], 422);
        }

        $directory = $this->workbookAudioSheetDirectory($userId, $validated['token'], $validated['code']);
        $disk = Storage::disk('local');
        $disk->deleteDirectory($directory);
        $storedPath = $file->storeAs($directory, 'audio.'.$extension, 'local');
        if (! is_string($storedPath)) {
            return response()->json(['message' => 'Không thể lưu tạm audio trên máy chủ.'], 500);
        }
        if (! $disk->put($directory.'/original-name.txt', $file->getClientOriginalName())) {
            $disk->deleteDirectory($directory);

            return response()->json(['message' => 'Không thể lưu tên file audio tạm.'], 500);
        }

        return response()->json(['code' => $validated['code'], 'stored' => true]);
    }

    public function discardWorkbookAudio(Request $request): mixed
    {
        $validated = $request->validate(['token' => ['required', 'uuid']]);
        $userId = (int) $request->user()->id;
        $this->deleteWorkbookAudioStaging($userId, $validated['token']);
        Cache::forget('question-workbook:'.$userId.':'.$validated['token']);

        return response()->json(['discarded' => true]);
    }

    public function confirmWorkbook(Request $request, QuestionWorkbookImportService $workbooks, MediaService $media): mixed
    {
        $validated = $request->validate(['token' => ['required', 'uuid']]);
        $cacheKey = 'question-workbook:'.$request->user()->id.':'.$validated['token'];
        $preview = Cache::get($cacheKey);
        abort_unless(is_array($preview), 404, 'Bản xem trước đã hết hạn. Hãy tải workbook lên lại.');
        if (in_array($preview['kind'] ?? null, ['writing', 'speaking'], true)) {
            return $this->confirmSubjectiveWorkbook($request, $cacheKey);
        }
        if (($preview['kind'] ?? null) === 'thpt_english') {
            return $this->confirmThptEnglishWorkbook($request, $preview, $cacheKey, $media);
        }

        if ($preview['kind'] === 'reading') {
            $readingInputs = $request->input('readings', []);
            $readingErrors = [];
            foreach ($preview['sheets'] as &$sheet) {
                if ($sheet['blocked'] || $sheet['questions'] === []) {
                    continue;
                }

                $input = is_array($readingInputs) ? ($readingInputs[$sheet['code']] ?? []) : [];
                $title = trim((string) (is_array($input) ? ($input['title'] ?? $sheet['reading_title']) : $sheet['reading_title']));
                $body = trim((string) (is_array($input) ? ($input['body'] ?? $sheet['reading_body']) : $sheet['reading_body']));
                if ($title === '') {
                    $readingErrors[$sheet['code']][] = 'Thêm tiêu đề cho bài đọc trước khi import.';
                } elseif (mb_strlen($title) > 255) {
                    $readingErrors[$sheet['code']][] = 'Tiêu đề bài đọc không được vượt quá 255 ký tự.';
                }
                if ($body === '') {
                    $readingErrors[$sheet['code']][] = 'Thêm nội dung đoạn văn cho bài đọc trước khi import.';
                } elseif (mb_strlen($body) > 50000) {
                    $readingErrors[$sheet['code']][] = 'Đoạn văn không được vượt quá 50.000 ký tự.';
                }

                $sheet['reading_title'] = $title;
                $sheet['reading_body'] = $body;
            }
            unset($sheet);

            if ($readingErrors !== []) {
                return response()->json(['message' => 'Hoàn thiện nội dung bài đọc trước khi xác nhận import.', 'sheets' => $readingErrors], 422);
            }
        }

        $audioFiles = $request->file('audio', []);
        if ($preview['kind'] === 'listening') {
            $disk = Storage::disk('local');
            foreach ($preview['sheets'] as $sheet) {
                if ($sheet['blocked'] || $sheet['questions'] === []) {
                    continue;
                }

                $directory = $this->workbookAudioSheetDirectory((int) $request->user()->id, $validated['token'], $sheet['code']);
                $stagedPath = collect($disk->files($directory))->first(fn (string $path): bool => preg_match('/^audio\\.(mp3|wav|m4a|ogg)$/i', basename(str_replace(chr(92), '/', $path))) === 1);
                if (! $stagedPath) {
                    continue;
                }

                $absolutePath = $disk->path($stagedPath);
                $audioFiles[$sheet['code']] = new UploadedFile(
                    $absolutePath,
                    $disk->get($directory.'/original-name.txt') ?: basename($stagedPath),
                    null,
                    UPLOAD_ERR_OK,
                    true,
                );
            }
        }
        $imageFiles = $request->file('images', []);
        $audioErrors = [];
        $requiresQuestionImages = static fn (array $sheet): bool => $preview['kind'] === 'listening'
            && ($sheet['exam_type'] ?? null) === 'toeic'
            && ($sheet['skill'] ?? null) === 'listening'
            && ($sheet['exam_part'] ?? null) === 'part-1';
        $maximumBytes = ((int) (app(SettingsService::class)->get('media')['max_megabytes'] ?? 50)) * 1024 * 1024;
        if ($preview['kind'] === 'listening') {
            foreach ($preview['sheets'] as $sheet) {
                if ($sheet['blocked'] || $sheet['questions'] === []) {
                    continue;
                }
                $file = $audioFiles[$sheet['code']] ?? null;
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    $audioErrors[$sheet['code']][] = 'Đính kèm một file audio cho sheet '.$sheet['code'].'.';
                } else {
                    $extension = strtolower($file->getClientOriginalExtension());
                    $mime = strtolower((string) $file->getMimeType());
                    $validMime = str_starts_with($mime, 'audio/') || ($extension === 'm4a' && $mime === 'video/mp4');
                    if (! in_array($extension, ['mp3', 'wav', 'm4a', 'ogg'], true) || ! $validMime) {
                        $audioErrors[$sheet['code']][] = 'File của sheet '.$sheet['code'].' phải là MP3, WAV, M4A hoặc OGG.';
                    }
                    if ($file->getSize() > $maximumBytes) {
                        $audioErrors[$sheet['code']][] = 'File audio của sheet '.$sheet['code'].' vượt giới hạn dung lượng.';
                    }
                }

                if ($requiresQuestionImages($sheet)) {
                    $sheetImages = $imageFiles[$sheet['code']] ?? [];
                    $sheetImages = is_array($sheetImages) ? $sheetImages : [];
                    foreach ($sheet['questions'] as $question) {
                        $image = $sheetImages[$question['row']] ?? null;
                        if (! $image instanceof UploadedFile || ! $image->isValid()) {
                            $audioErrors[$sheet['code']][] = 'Đính kèm ảnh cho câu ở dòng '.$question['row'].' của sheet '.$sheet['code'].'.';

                            continue;
                        }
                        $imageExtension = strtolower($image->getClientOriginalExtension());
                        $imageMime = strtolower((string) $image->getMimeType());
                        if (! in_array($imageExtension, ['jpg', 'jpeg', 'png', 'webp'], true)
                            || ! in_array($imageMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                            $audioErrors[$sheet['code']][] = 'Ảnh dòng '.$question['row'].' của sheet '.$sheet['code'].' phải là JPG, PNG hoặc WEBP.';
                        }
                        if ($image->getSize() > $maximumBytes) {
                            $audioErrors[$sheet['code']][] = 'Ảnh dòng '.$question['row'].' của sheet '.$sheet['code'].' vượt giới hạn dung lượng.';
                        }
                    }
                }
            }
        }
        if ($audioErrors !== []) {
            return response()->json(['message' => 'Cần xử lý file audio hoặc ảnh trước khi xác nhận.', 'sheets' => $audioErrors], 422);
        }
        if ((int) $preview['total_questions'] < 1) {
            return response()->json(['message' => 'Workbook chưa có câu hỏi hợp lệ.', 'sheets' => []], 422);
        }

        $imported = 0;
        $errors = [];
        foreach ($preview['sheets'] as $sheet) {
            foreach ($sheet['errors'] as $error) {
                $errors[$sheet['code']][] = 'Dòng '.$error['row'].': '.$error['message'];
            }
        }
        foreach ($preview['sheets'] as $sheet) {
            if ($sheet['blocked'] || $sheet['questions'] === []) {
                continue;
            }
            if ($workbooks->codeExists($preview['kind'], $sheet['code'])) {
                $errors[$sheet['code']][] = 'Mã bài đã tồn tại trong ngân hàng. Tải workbook lên lại để xem trước mã mới.';

                continue;
            }

            if ($preview['kind'] === 'reading') {
                $readingStates = [];
                foreach ($sheet['questions'] as $index => $question) {
                    $row = [
                        'reading_key' => $sheet['code'],
                        'reading_title' => $index === 0 ? $sheet['reading_title'] : '',
                        'reading_body' => $index === 0 ? $sheet['reading_body'] : '',
                    ];
                    try {
                        $reading = $this->persistCsvReadingQuestion($question['attributes'], $row, $readingStates);
                        $readingStates[$this->csvReadingStateKey((int) $question['attributes']['exam_part_id'], $sheet['code'])] = $reading;
                        $imported++;
                    } catch (ValidationException $exception) {
                        $errors[$sheet['code']][] = 'Dòng '.$question['row'].': '.collect($exception->errors())->flatten()->first();
                    } catch (Throwable $exception) {
                        report($exception);
                        $errors[$sheet['code']][] = 'Dòng '.$question['row'].': Không thể lưu câu hỏi.';
                    }
                }

                continue;
            }

            $asset = null;
            $groupId = null;
            $sheetImported = 0;
            try {
                $asset = $media->store($audioFiles[$sheet['code']], $request->user()->id);
                DB::transaction(function () use (&$groupId, &$sheetImported, &$errors, $sheet, $asset, $requiresQuestionImages, $media, $imageFiles, $request): void {
                    foreach ($sheet['questions'] as $question) {
                        $creatingGroup = $groupId === null;
                        $imageAsset = null;
                        try {
                            if ($requiresQuestionImages($sheet)) {
                                $sheetImages = $imageFiles[$sheet['code']] ?? [];
                                $imageFile = is_array($sheetImages) ? ($sheetImages[$question['row']] ?? null) : null;
                                if (! $imageFile instanceof UploadedFile) {
                                    throw ValidationException::withMessages(['images' => 'Thiếu ảnh ở dòng '.$question['row'].'.']);
                                }
                                $imageAsset = $media->store($imageFile, $request->user()->id);
                            }
                            DB::transaction(function () use (&$groupId, $sheet, $question, $imageAsset): void {
                                if ($groupId === null) {
                                    $part = ExamPart::with(['examType', 'skill'])->findOrFail($sheet['exam_part_id']);
                                    $groupKind = match ([$part->examType->code, $part->code]) {
                                        ['toeic', 'part-3'] => 'conversation',
                                        ['toeic', 'part-4'] => 'talk',
                                        default => $part->examType->code === 'ielts' ? 'ielts_section' : 'question_group',
                                    };
                                    $groupId = DB::table('question_groups')->insertGetId([
                                        'title' => $sheet['code'],
                                        'kind' => $groupKind,
                                        'exam_part_id' => $part->id,
                                        'stimulus_ids' => json_encode([], JSON_THROW_ON_ERROR),
                                        'metadata' => json_encode(['source' => 'xlsx_import', 'lesson_code' => $sheet['code'], 'exam_type' => $part->examType->code, 'skill' => $part->skill->code], JSON_THROW_ON_ERROR),
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);
                                }
                                $attributes = $question['attributes'];
                                $attributes['question_group_id'] = (int) $groupId;
                                $attributes['group_order'] = (int) DB::table('question_bank_items')->whereNull('deleted_at')->where('question_group_id', $groupId)->count();
                                if ($imageAsset) {
                                    $attributes['metadata'] = array_replace($attributes['metadata'] ?? [], ['image_id' => $imageAsset->id]);
                                }
                                $this->persistQuestion($attributes);
                            });
                            $sheetImported++;
                        } catch (ValidationException $exception) {
                            if ($imageAsset) {
                                $media->delete($imageAsset);
                            }
                            if ($creatingGroup) {
                                $groupId = null;
                            }
                            $errors[$sheet['code']][] = 'Dòng '.$question['row'].': '.collect($exception->errors())->flatten()->first();
                        } catch (Throwable $exception) {
                            if ($imageAsset) {
                                $media->delete($imageAsset);
                            }
                            if ($creatingGroup) {
                                $groupId = null;
                            }
                            report($exception);
                            $errors[$sheet['code']][] = 'Dòng '.$question['row'].': Không thể lưu câu hỏi.';
                        }
                    }
                    if ($sheetImported > 0 && $groupId !== null) {
                        app(QuestionAudioService::class)->assignToGroup((int) $groupId, $asset->id);
                    }
                });
                if ($sheetImported > 0) {
                    $imported += $sheetImported;
                } else {
                    $media->delete($asset);
                }
            } catch (Throwable $exception) {
                if ($asset) {
                    $media->delete($asset);
                }
                report($exception);
                $errors[$sheet['code']][] = 'Không thể lưu bài Listening hoặc file audio.';
            }
        }

        Cache::forget($cacheKey);
        if ($preview['kind'] === 'listening') {
            $this->deleteWorkbookAudioStaging((int) $request->user()->id, $validated['token']);
        }
        $summary = ['total' => $preview['total_questions'], 'imported' => $imported, 'failed' => collect($errors)->flatten()->count()];
        $redirect = route($preview['kind'] === 'listening' ? 'admin.questions.listening' : 'admin.readings.index');
        $request->session()->flash('import_summary', $summary);
        $request->session()->flash('import_errors', collect($errors)->flatMap(fn (array $messages, string $code) => collect($messages)->map(fn (string $message) => $code.': '.$message))->values()->all());

        return response()->json(['summary' => $summary, 'errors' => $errors, 'redirect' => $redirect]);
    }

    private function confirmSubjectiveWorkbook(Request $request, string $cacheKey): mixed
    {
        set_time_limit(0);
        $lock = Cache::lock($cacheKey.':confirm', 3600);
        abort_unless($lock->get(), 409, 'Workbook đang được import. Chờ hoàn tất rồi tải lại danh sách.');
        try {
            $preview = Cache::get($cacheKey);
            abort_unless(is_array($preview), 404, 'Bản xem trước đã hết hạn. Hãy tải workbook lên lại.');
            if (isset($preview['completed_response'])) return response()->json($preview['completed_response']);
            abort_unless($preview['can_import'] ?? false, 422, 'Sửa hết lỗi theo sheet/dòng và tải lại workbook trước khi import.');
            DB::transaction(function () use ($preview): void {
                foreach ($preview['sheets'] as $sheet) {
                    foreach ($sheet['questions'] as $question) {
                        try {
                            $payload = $this->questionPayload($question['attributes']);
                            abort_unless($payload['skill'] === $preview['kind'], 422, 'Part đã thay đổi kỹ năng. Hãy tải lại workbook.');
                            app(QuestionRegistry::class)->validate($payload);
                            $this->persistQuestion($question['attributes']);
                        } catch (ValidationException $exception) {
                            throw ValidationException::withMessages(['file' => $sheet['code'].' · Dòng '.$question['row'].': '.collect($exception->errors())->flatten()->implode(' ')]);
                        }
                    }
                }
            });
            $count = $preview['total_questions'];
            $result = ['summary' => ['total' => $count, 'imported' => $count, 'failed' => 0], 'errors' => [], 'redirect' => route('admin.questions.'.$preview['kind'])];
            $preview['completed_response'] = $result;
            Cache::put($cacheKey, $preview, now()->addMinutes(30));
            $request->session()->flash('status', 'Đã import '.$count.' câu hỏi '.ucfirst($preview['kind']).' ở trạng thái bản nháp.');

            return response()->json($result);
        } finally {
            $lock->release();
        }
    }

    /** Import one complete six-part THPT English paper after its workbook and shared images pass preview. */
    private function confirmThptEnglishWorkbook(Request $request, array $preview, string $cacheKey, MediaService $media): mixed
    {
        if (! ($preview['can_import'] ?? false) || (int) ($preview['total_questions'] ?? 0) !== 40) {
            $sheetErrors = collect($preview['sheets'] ?? [])
                ->filter(fn (array $sheet): bool => $sheet['errors'] !== [])
                ->map(fn (array $sheet): array => collect($sheet['errors'])->map(fn (array $error): string => 'Dòng '.$error['row'].': '.$error['message'])->all())
                ->all();

            return response()->json([
                'message' => 'Workbook cần đủ 6 phần và 40 câu hợp lệ trước khi import.',
                'sheets' => $sheetErrors,
            ], 422);
        }

        $imageFiles = $request->file('group_images', []);
        $imageErrors = [];
        $maximumBytes = ((int) (app(SettingsService::class)->get('media')['max_megabytes'] ?? 50)) * 1024 * 1024;
        foreach (['tieng-anh-part-1' => 'PART_1', 'tieng-anh-part-2' => 'PART_2'] as $partCode => $sheetCode) {
            $image = $imageFiles[$partCode] ?? null;
            if (! $image instanceof UploadedFile || ! $image->isValid()) {
                $imageErrors[$sheetCode][] = 'Chọn ảnh văn bản cho '.$sheetCode.' trước khi import.';

                continue;
            }

            $extension = strtolower($image->getClientOriginalExtension());
            $mime = strtolower((string) $image->getMimeType());
            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
                || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $imageErrors[$sheetCode][] = 'Ảnh '.$sheetCode.' phải là JPG, PNG hoặc WEBP.';
            }
            if ($image->getSize() > $maximumBytes) {
                $imageErrors[$sheetCode][] = 'Ảnh '.$sheetCode.' vượt giới hạn dung lượng.';
            }
        }
        if ($imageErrors !== []) {
            return response()->json(['message' => 'Cần chọn đúng ảnh cho Phần 1 và Phần 2.', 'sheets' => $imageErrors], 422);
        }

        $assets = [];
        try {
            foreach (['tieng-anh-part-1', 'tieng-anh-part-2'] as $partCode) {
                $assets[$partCode] = $media->store($imageFiles[$partCode], $request->user()->id);
            }

            DB::transaction(function () use ($preview, $assets): void {
                $readingStates = [];
                foreach ($preview['sheets'] as $sheet) {
                    $isSharedTextPart = in_array($sheet['part_code'], [
                        'tieng-anh-part-1', 'tieng-anh-part-2', 'tieng-anh-part-4', 'tieng-anh-part-5', 'tieng-anh-part-6',
                    ], true);

                    foreach ($sheet['questions'] as $index => $question) {
                        $attributes = $question['attributes'];
                        if (isset($assets[$sheet['part_code']])) {
                            $attributes['metadata'] = array_replace($attributes['metadata'] ?? [], ['image_id' => (int) $assets[$sheet['part_code']]->id]);
                        }

                        if ($isSharedTextPart) {
                            $row = [
                                'reading_key' => $sheet['part_code'],
                                'reading_title' => $index === 0 ? $sheet['reading_title'] : '',
                                'reading_body' => $index === 0 ? $sheet['reading_body'] : '',
                            ];
                            $reading = $this->persistCsvReadingQuestion($attributes, $row, $readingStates);
                            $readingStates[$this->csvReadingStateKey((int) $attributes['exam_part_id'], $sheet['part_code'])] = $reading;

                            continue;
                        }

                        app(QuestionRegistry::class)->validate($this->questionPayload($attributes));
                        $this->persistQuestion($attributes);
                    }
                }
            });
        } catch (Throwable $exception) {
            foreach ($assets as $asset) {
                try {
                    $media->delete($asset);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            report($exception);

            $message = $exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->first()
                : 'Không thể lưu đủ 40 câu. Hãy kiểm tra cấu hình lưu ảnh và thử lại.';

            return response()->json(['message' => $message, 'sheets' => []], 422);
        }

        Cache::forget($cacheKey);
        $summary = ['total' => 40, 'imported' => 40, 'failed' => 0];
        $request->session()->flash('import_summary', $summary);

        return response()->json(['summary' => $summary, 'errors' => [], 'redirect' => route('admin.questions.thpt')]);
    }

    public function importTemplate(Request $request, QuestionWorkbookImportService $workbooks, ThptEnglishWorkbookImportService $thptWorkbooks): mixed
    {
        $template = $request->string('template', 'all')->toString();
        if (in_array($template, ['writing-xlsx', 'speaking-xlsx'], true)) {
            $kind = str_replace('-xlsx', '', $template);
            $spreadsheet = app(SubjectiveQuestionWorkbookService::class)->template($kind);
            $writer = $workbooks->writer($spreadsheet);

            return response()->streamDownload(function () use ($writer, $spreadsheet): void {
                $writer->save('php://output');
                $spreadsheet->disconnectWorksheets();
            }, 'vistiq-'.$kind.'-question-template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }
        if ($template === 'thpt-english-xlsx') {
            $spreadsheet = $thptWorkbooks->template();
            $writer = $thptWorkbooks->writer($spreadsheet);

            return response()->streamDownload(function () use ($writer, $spreadsheet): void {
                $writer->save('php://output');
                $spreadsheet->disconnectWorksheets();
            }, 'vistiq-thpt-english-40-questions-template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }
        if (in_array($template, ['listening-xlsx', 'reading-xlsx'], true)) {
            $kind = str_replace('-xlsx', '', $template);
            $spreadsheet = $workbooks->template($kind);
            $writer = $workbooks->writer($spreadsheet);

            return response()->streamDownload(function () use ($writer, $spreadsheet): void {
                $writer->save('php://output');
                $spreadsheet->disconnectWorksheets();
            }, 'vistiq-'.$kind.'-question-template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }
        if (str_starts_with($template, 'type-')) {
            $questionType = substr($template, 5);
            abort_unless(isset(QuestionRegistry::TYPES[$questionType]), 404);
            $filename = 'by-question-type/'.$questionType.'.csv';
        } else {
            abort_unless(isset(self::CSV_TEMPLATES[$template]), 404);
            $filename = self::CSV_TEMPLATES[$template];
        }
        $contentType = str_ends_with($filename, '.csv') ? 'text/csv; charset=UTF-8' : (str_ends_with($filename, '.md') ? 'text/markdown; charset=UTF-8' : 'application/zip');

        return response()->download(resource_path('import-templates/questions/'.$filename), basename($filename), ['Content-Type' => $contentType]);
    }

    public function edit(QuestionBankItem $question): View
    {
        $question->load(['learningItem', 'examPart.examType', 'questionType', 'difficulty', 'stimulus', 'options', 'acceptedAnswers']);

        return $this->renderScreen('admin-question-edit', self::SCREENS['admin-question-edit'])->with($this->editorData($question));
    }

    public function update(QuestionUpsertRequest $request, QuestionBankItem $question): mixed
    {
        $revision = DB::transaction(function () use ($request, $question) {
            $attributes = $this->embeddedAttributes($request, $question);
            $revision = app(ContentRevisionService::class)->save('question', $question, $this->questionPayload($attributes), $attributes['revision_counter'] ?? null);
            $question->refresh();
            if (! $question->published_revision_id) {
                $question->forceFill(['exam_part_id' => $attributes['exam_part_id'], 'question_type_id' => $attributes['question_type_id'], 'question_difficulty_id' => $attributes['question_difficulty_id'], 'learning_stimulus_id' => $attributes['learning_stimulus_id'] ?? null, 'prompt' => $revision->payload['prompt'], 'explanation' => $revision->payload['explanation'] ?? null, 'question_group_id' => $attributes['question_group_id'] ?? null, 'group_order' => $attributes['group_order'] ?? 0])->save();
                $this->syncAnswers($question, $attributes, QuestionType::findOrFail($attributes['question_type_id']));
            }
            $this->syncTags($question->learningItem, $attributes['tags'] ?? '');

            return $revision;
        });
        if ($request->expectsJson() && $request->filled('editor_stimulus_id')) {
            return $this->savedResponse($question->fresh(), $request);
        }
        if ($request->expectsJson()) {
            return response()->json(['revision_counter' => $revision->number, 'status' => $revision->status, 'updated_at' => $revision->created_at->format('d/m/Y H:i'), 'history' => app(QuestionEditorService::class)->history($question), 'workflow_html' => view('shared.workflow-actions', ['kind' => 'question', 'entityId' => $question->id, 'revision' => $revision])->render()]);
        }

        return redirect()->route('admin.questions.edit', array_filter(['question' => $question->id, 'from' => $request->input('return_to') === 'thpt' ? 'thpt' : null]))->with('status', 'Đã lưu bản nháp.');
    }

    public function preview(QuestionUpsertRequest $request): mixed
    {
        $payload = $this->questionPayload($request->validated());
        $warnings = [];
        try {
            app(QuestionRegistry::class)->validate($payload);
        } catch (ValidationException $exception) {
            $warnings = array_merge(...array_values($exception->errors()));
        }

        return response()->json(['html' => view('pages.admin.assessment.question-bank.questions.editor-preview', $this->previewData($payload))->render(), 'warnings' => $warnings]);
    }

    private function previewData(array $payload): array
    {
        $registry = app(QuestionRegistry::class);
        $payload['prompt'] = $registry->sanitize($payload['prompt'] ?? '');
        $payload['explanation'] = $registry->sanitize($payload['explanation'] ?? '');
        $payload['options'] = array_map(fn ($option) => $registry->sanitize((string) $option), $payload['options'] ?? []);
        $part = ExamPart::with('examType')->find($payload['exam_part_id'] ?? null);
        $stimulus = LearningStimulus::find($payload['stimulus_ids'][0] ?? null);
        $stimulusPayload = $stimulus ? app(ContentRevisionService::class)->payload('stimulus', $stimulus) : [];

        return [
            'previewPayload' => $payload, 'previewPart' => $part,
            'previewDifficulty' => QuestionDifficulty::find($payload['question_difficulty_id'] ?? null),
            'previewKnowledge' => LearningItem::whereIn('id', $payload['metadata']['knowledge_ids'] ?? [])->get(),
            'previewImage' => MediaAsset::find($payload['metadata']['image_id'] ?? null),
            'previewAudio' => MediaAsset::find($payload['metadata']['audio_id'] ?? ($stimulus?->stimulus_type === 'audio' ? ($stimulusPayload['metadata']['asset_id'] ?? null) : null)),
            'previewStimulus' => $stimulus,
            'previewStimulusPayload' => $stimulusPayload,
        ];
    }

    private function persistQuestion(array $attributes): QuestionBankItem
    {
        $learningItem = LearningItem::create(['learning_item_type_id' => $this->questionLearningItemTypeId(), 'canonical_text' => $this->canonicalQuestionText($attributes['prompt'] ?? ''), 'slug' => 'question-'.Str::uuid(), 'created_by' => auth()->id(), 'status' => 'draft']);
        $question = QuestionBankItem::create(['learning_item_id' => $learningItem->id, 'exam_part_id' => $attributes['exam_part_id'], 'question_type_id' => $attributes['question_type_id'], 'question_difficulty_id' => $attributes['question_difficulty_id'], 'learning_stimulus_id' => $attributes['learning_stimulus_id'] ?? null, 'prompt' => app(QuestionRegistry::class)->sanitize($attributes['prompt'] ?? ''), 'explanation' => $attributes['explanation'] ?? null]);
        $question->forceFill(['question_group_id' => $attributes['question_group_id'] ?? null, 'group_order' => $attributes['group_order'] ?? 0])->save();
        $this->syncAnswers($question, $attributes, QuestionType::findOrFail($attributes['question_type_id']));
        $this->syncTags($learningItem, $attributes['tags'] ?? '');
        app(ContentRevisionService::class)->save('question', $question, $this->questionPayload($attributes), 0);

        return $question;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, array{id: int, exam_part_id: int, next_order: int}>  $audioGroups
     * @return array{question: QuestionBankItem, audio_group: array{id: int, exam_part_id: int, next_order: int}|null}
     */
    private function persistCsvQuestion(array $attributes, string $audioGroupName, array $audioGroups): array
    {
        return DB::transaction(function () use ($attributes, $audioGroupName, $audioGroups): array {
            if ($audioGroupName === '') {
                return ['question' => $this->persistQuestion($attributes), 'audio_group' => null];
            }

            $groupKey = Str::lower(trim($audioGroupName));
            $audioGroup = $audioGroups[$groupKey] ?? null;

            if ($audioGroup === null) {
                $part = ExamPart::with(['examType', 'skill'])->findOrFail($attributes['exam_part_id']);
                $kind = match ([$part->examType->code, $part->code]) {
                    ['toeic', 'part-3'] => 'conversation',
                    ['toeic', 'part-4'] => 'talk',
                    default => $part->examType->code === 'ielts' ? 'ielts_section' : 'question_group',
                };
                $groupId = DB::table('question_groups')->insertGetId([
                    'title' => trim($audioGroupName),
                    'kind' => $kind,
                    'exam_part_id' => $part->id,
                    'stimulus_ids' => json_encode([], JSON_THROW_ON_ERROR),
                    'metadata' => json_encode(['source' => 'csv_import', 'audio_group' => trim($audioGroupName)], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $audioGroup = ['id' => (int) $groupId, 'exam_part_id' => (int) $part->id, 'next_order' => 0];
            } elseif ($audioGroup['exam_part_id'] !== (int) $attributes['exam_part_id']) {
                throw ValidationException::withMessages(['audio_group' => 'Tất cả câu trong một audio_group phải thuộc cùng exam_part.']);
            }

            $attributes['question_group_id'] = $audioGroup['id'];
            $attributes['group_order'] = $audioGroup['next_order'];
            $question = $this->persistQuestion($attributes);
            $audioGroup['next_order']++;

            return ['question' => $question, 'audio_group' => $audioGroup];
        });
    }

    /** Create an article and its questions together; the map survives CSV batches. */
    private function persistCsvReadingQuestion(array $attributes, array $row, array $readings): array
    {
        $key = trim((string) ($row['reading_key'] ?? ''));
        $part = ExamPart::with(['examType', 'skill'])->findOrFail($attributes['exam_part_id']);
        $reading = $readings[$this->csvReadingStateKey((int) $part->id, $key)] ?? null;
        $data = Validator::make([
            'reading_key' => $key,
            'reading_title' => trim((string) ($row['reading_title'] ?? '')) ?: ($reading['title'] ?? ''),
            'reading_body' => trim((string) ($row['reading_body'] ?? '')) ?: ($reading['body'] ?? ''),
        ], [
            'reading_key' => ['required', 'string', 'max:100'],
            'reading_title' => ['required', 'string', 'max:255'],
            'reading_body' => ['required', 'string', 'max:50000'],
        ], [
            'reading_key.required' => 'Điền reading_key để gom các câu hỏi của bài đọc.',
            'reading_title.required' => 'Dòng đầu tiên của bài cần reading_title.',
            'reading_body.required' => 'Dòng đầu tiên của bài cần reading_body.',
        ])->validate();
        $isThptSharedTextPart = $part->examType?->code === 'thpt'
            && in_array($part->code, ['tieng-anh-part-1', 'tieng-anh-part-2', 'tieng-anh-part-4', 'tieng-anh-part-5', 'tieng-anh-part-6'], true);
        if ($part->skill->code !== 'reading' && ! $isThptSharedTextPart) {
            throw ValidationException::withMessages(['exam_part' => 'Bài dùng reading_key phải thuộc Reading hoặc phần Tiếng Anh THPT có văn bản dùng chung.']);
        }
        if (filled($row['stimulus'] ?? null) || filled($attributes['learning_stimulus_id'] ?? null)
            || filled($attributes['audio_group'] ?? null) || filled($attributes['question_group_id'] ?? null)) {
            throw ValidationException::withMessages(['reading_key' => 'Tạo bài bằng reading_key không dùng đồng thời stimulus, audio_group hoặc payload_json.group_id/stimulus_ids.']);
        }
        if ($reading && ($reading['exam_part_id'] !== (int) $part->id
            || $reading['title'] !== $data['reading_title'] || $reading['body'] !== $data['reading_body'])) {
            throw ValidationException::withMessages(['reading_key' => 'Các dòng cùng reading_key phải có cùng exam_part, reading_title và reading_body.']);
        }
        app(QuestionRegistry::class)->validate($this->questionPayload($attributes));

        return DB::transaction(function () use ($attributes, $data, $part, $reading): array {
            if ($reading === null) {
                $stimulus = LearningStimulus::create([
                    'title' => $data['reading_title'], 'stimulus_type' => 'reading',
                    'body' => app(QuestionRegistry::class)->sanitize($data['reading_body']),
                    'status' => 'draft', 'created_by' => auth()->id(),
                ]);
                $groupId = $this->createStimulusGroup($stimulus, $part->id);
                app(ContentRevisionService::class)->save('stimulus', $stimulus, [
                    'title' => $data['reading_title'], 'stimulus_type' => 'reading', 'body' => $data['reading_body'],
                    'metadata' => ['exam_part_id' => $part->id, 'content_kind' => $part->examType->code === 'toeic' && $part->code === 'part-6' ? 'paragraph' : 'passage', 'question_group_id' => $groupId, 'lesson_code' => $data['reading_key']],
                ], 0);
                $reading = ['id' => (int) $stimulus->id, 'group_id' => $groupId, 'exam_part_id' => (int) $part->id,
                    'title' => $data['reading_title'], 'body' => $data['reading_body'], 'next_order' => 0];
            }
            $attributes['learning_stimulus_id'] = $reading['id'];
            $attributes['question_group_id'] = $reading['group_id'];
            $attributes['group_order'] = $reading['next_order'];
            $this->persistQuestion($attributes);
            $reading['next_order']++;

            return $reading;
        });
    }

    private function csvReadingStateKey(int $partId, string $readingKey): string
    {
        return Str::lower(trim($readingKey));
    }

    private function questionPayload(array $attributes): array
    {
        $part = ExamPart::with(['examType', 'skill'])->findOrFail($attributes['exam_part_id']);

        return ['exam_part_id' => $part->id, 'exam_type' => $part->examType->code, 'part' => $part->code, 'skill' => $part->skill->code, 'type' => QuestionType::findOrFail($attributes['question_type_id'])->code, 'prompt' => $attributes['prompt'] ?? '', 'explanation' => $attributes['explanation'] ?? '', 'options' => array_filter($attributes['options'] ?? [], fn ($v) => trim((string) $v) !== ''), 'correct_options' => $attributes['correct_options'] ?? array_values(array_filter([$attributes['correct_option'] ?? null])), 'accepted_answers' => array_values(array_filter($attributes['accepted_answers'] ?? [])), 'metadata' => $attributes['metadata'] ?? [], 'stimulus_ids' => $attributes['stimulus_ids'] ?? array_values(array_filter([$attributes['learning_stimulus_id'] ?? null])), 'group_id' => $attributes['question_group_id'] ?? null, 'group_order' => $attributes['group_order'] ?? 0, 'question_difficulty_id' => $attributes['question_difficulty_id'], 'tags' => $attributes['tags'] ?? ''];
    }

    /** @param Collection<int, object> $items */
    private function catalogMap(Collection $items, string $key): Collection
    {
        return $items->mapWithKeys(fn (object $item): array => [Str::lower(trim((string) $item->{$key})) => (int) $item->id]);
    }

    private function resolveCatalogId(Collection $map, ?string $value): ?int
    {
        $normalized = Str::lower(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        if ($map->has($normalized)) {
            return (int) $map->get($normalized);
        }

        if (ctype_digit($normalized) && $map->contains((int) $normalized)) {
            return (int) $normalized;
        }

        return null;
    }

    /** @param array<int, string> $fields */
    private function isBlankCsvRow(array $fields): bool
    {
        return collect($fields)->every(fn ($value): bool => trim((string) $value) === '');
    }

    private function detectCsvDelimiter(string $line): string
    {
        $delimiters = [',', ';', "\t"];
        usort($delimiters, fn (string $left, string $right): int => substr_count($line, $right) <=> substr_count($line, $left));

        return $delimiters[0];
    }

    private function countCsvRows(mixed $handle, string $delimiter): int
    {
        $total = 0;
        while (($fields = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if (! $this->isBlankCsvRow($fields)) {
                $total++;
            }
        }
        rewind($handle);
        fgets($handle);

        return $total;
    }

    private function importProgressKey(int $userId, string $token): string
    {
        $safeToken = Str::isUuid($token) ? $token : 'no-token';

        return 'question-import:'.$userId.':'.$safeToken;
    }

    private function workbookAudioUploadLimitBytes(): int
    {
        $configuredAudioBytes = ((int) (app(SettingsService::class)->get('media')['max_megabytes'] ?? 50)) * 1024 * 1024;
        $uploadLimit = function_exists('ini_parse_quantity') ? (int) ini_parse_quantity((string) ini_get('upload_max_filesize')) : 0;
        $postLimit = function_exists('ini_parse_quantity') ? (int) ini_parse_quantity((string) ini_get('post_max_size')) : 0;
        $uploadLimit = $uploadLimit > 0 ? $uploadLimit : PHP_INT_MAX;
        $postLimit = $postLimit > 0 ? max(0, $postLimit - 262144) : PHP_INT_MAX;

        return max(1, min($configuredAudioBytes, $uploadLimit, $postLimit));
    }

    private function workbookAudioDirectory(int $userId, string $token): string
    {
        $safeToken = Str::isUuid($token) ? $token : 'no-token';

        return 'question-workbook-imports/'.$userId.'/'.$safeToken;
    }

    private function workbookAudioSheetDirectory(int $userId, string $token, string $code): string
    {
        $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '_', $code) ?: 'unknown';

        return $this->workbookAudioDirectory($userId, $token).'/'.$safeCode;
    }

    private function deleteWorkbookAudioStaging(int $userId, string $token): void
    {
        Storage::disk('local')->deleteDirectory($this->workbookAudioDirectory($userId, $token));
    }

    private function pruneExpiredWorkbookAudio(int $userId): void
    {
        $disk = Storage::disk('local');
        $userDirectory = 'question-workbook-imports/'.$userId;
        $expiredAt = now()->subMinutes(30)->getTimestamp();
        foreach ($disk->directories($userDirectory) as $directory) {
            try {
                if ($disk->lastModified($directory) < $expiredAt) {
                    $disk->deleteDirectory($directory);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /** @return list<string> */
    private function missingResources(QuestionBankItem $question): array
    {
        $payload = $question->draftRevision?->payload ?? $question->publishedRevision?->payload ?? [];
        $metadata = $payload['metadata'] ?? [];
        $stimulus = $question->stimulus;
        $missing = [];

        if ($question->examPart?->skill?->code === 'listening' && empty($metadata['audio_id']) && ! ($stimulus?->stimulus_type === 'audio' && (! empty($stimulus->metadata['asset_id']) || filled($stimulus->asset_path)))) {
            $missing[] = 'audio';
        }
        if ((in_array($question->questionType?->code, ['diagram_label_completion', 'plan_map_labelling'], true) || ($question->examPart?->examType?->code === 'toeic' && $question->examPart?->code === 'part-1')) && empty($metadata['image_id'])) {
            $missing[] = 'ảnh';
        }

        return $missing;
    }

    /** @param array<int, string> $headers */
    private function hasInvalidCsvHeaders(array $headers): bool
    {
        return $headers === [] || collect($headers)->contains('') || count($headers) !== count(array_unique($headers));
    }

    private function normalizeCsvHeader(string $header): string
    {
        $header = trim(ltrim($header, "\xEF\xBB\xBF"));
        $header = (string) preg_replace('/[\s\-]+/', '_', Str::lower($header));

        return self::CSV_HEADER_ALIASES[$header] ?? $header;
    }

    /**
     * @param  array<string, string>  $row
     * @param  Collection<string, int>  $partMap
     * @param  Collection<string, int>  $questionTypeMap
     * @param  Collection<string, int>  $difficultyMap
     * @param  Collection<string, int>  $stimulusMap
     * @return array<string, mixed>
     */
    private function attributesFromCsvRow(
        array $row,
        Collection $partMap,
        Collection $questionTypeMap,
        Collection $difficultyMap,
        Collection $stimulusMap,
        int $defaultQuestionType,
        int $defaultDifficulty,
    ): array {
        $options = collect([
            'A' => $row['option_a'] ?? '',
            'B' => $row['option_b'] ?? '',
            'C' => $row['option_c'] ?? '',
            'D' => $row['option_d'] ?? '',
        ])->map(fn ($option): string => trim((string) $option))->filter()->all();
        $acceptedAnswers = preg_split('/\s*[|;]\s*/', trim((string) ($row['accepted_answers'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        $questionType = trim((string) ($row['question_type'] ?? ''));
        $difficulty = trim((string) ($row['difficulty'] ?? ''));

        return [
            'exam_type' => Str::lower(trim((string) ($row['exam_type'] ?? ''))),
            'skill' => Str::lower(trim((string) ($row['skill'] ?? ''))),
            'exam_part_id' => $this->resolveCatalogId($partMap, $row['exam_part'] ?? null),
            'question_type_id' => $questionType === '' ? $defaultQuestionType : $this->resolveCatalogId($questionTypeMap, $questionType),
            'question_difficulty_id' => $difficulty === '' ? $defaultDifficulty : $this->resolveCatalogId($difficultyMap, $difficulty),
            'learning_stimulus_id' => $this->resolveCatalogId($stimulusMap, $row['stimulus'] ?? null),
            'prompt' => trim((string) ($row['prompt'] ?? '')),
            'options' => $options,
            'correct_option' => Str::upper(trim((string) ($row['correct_option'] ?? ''))),
            'accepted_answers' => $acceptedAnswers === false ? [] : array_map('trim', $acceptedAnswers),
            'explanation' => trim((string) ($row['explanation'] ?? '')),
            'tags' => trim((string) ($row['tags'] ?? '')),
            'status' => 'draft',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>  $row
     * @param  Collection<int, ExamPart>  $parts
     * @param  Collection<int, QuestionType>  $questionTypes
     */
    private function validateCsvClassification(array $attributes, array $row, Collection $parts, Collection $questionTypes): void
    {
        $part = $parts->firstWhere('id', $attributes['exam_part_id']);
        $questionType = $questionTypes->firstWhere('id', $attributes['question_type_id']);
        $errors = [];

        if ($attributes['exam_type'] !== '' && $attributes['exam_type'] !== $part->examType->code) {
            $errors['exam_type'] = 'exam_type không khớp exam_part; '.$part->code.' thuộc '.$part->examType->name.'.';
        }
        if ($attributes['skill'] !== '' && $attributes['skill'] !== $part->skill->code) {
            $errors['skill'] = 'skill không khớp exam_part; '.$part->code.' thuộc '.$part->skill->code.'.';
        }
        if (! in_array($questionType->code, app(QuestionRegistry::class)->allowedTypes($part->examType->code, $part->skill->code), true)) {
            $errors['question_type'] = 'Loại '.$questionType->code.' không phù hợp '.$part->examType->name.' / '.$part->skill->code.'.';
        }
        if ($part->skill->code !== 'listening' && filled($row['stimulus'] ?? null) && ! $attributes['learning_stimulus_id']) {
            $errors['stimulus'] = 'Không tìm thấy passage; dùng ID hoặc tiêu đề chính xác trong kho tài nguyên.';
        }
        if (filled($attributes['audio_group'] ?? null) && $part->skill->code !== 'listening') {
            $errors['audio_group'] = 'audio_group chỉ dùng để gom câu hỏi Listening.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, mixed> */
    public function editorData(?QuestionBankItem $question = null, ?LearningStimulus $contextStimulus = null): array
    {
        $payload = $question ? app(ContentRevisionService::class)->payload('question', $question) : [];
        if (! $question && $contextStimulus) {
            $context = app(ContentRevisionService::class)->payload('stimulus', $contextStimulus);
            $payload = ['exam_part_id' => $context['metadata']['exam_part_id'] ?? null, 'stimulus_ids' => [$contextStimulus->id], 'group_id' => $context['metadata']['question_group_id'] ?? null];
        }
        $editor = app(QuestionEditorService::class);
        $parts = ExamPart::with(['examType', 'skill'])->when($contextStimulus?->stimulus_type === 'reading', fn ($query) => $query->whereHas('skill', fn ($skills) => $skills->where('code', 'reading')))->orderBy('exam_type_id')->orderBy('sort_order')->get();
        $currentPart = $parts->firstWhere('id', (int) ($payload['exam_part_id'] ?? request()->integer('exam_part_id')));
        $editorSkill = $currentPart?->skill?->code ?? request()->query('skill');
        if (in_array($editorSkill, ['writing', 'speaking'], true) && ! $contextStimulus) {
            $parts = $parts->filter(fn ($part) => $part->skill?->code === $editorSkill)->values();
            if (! $question) {
                $currentPart ??= $parts->first();
                $payload['exam_part_id'] = $currentPart?->id;
                $payload['type'] = $editorSkill === 'writing' ? 'essay' : 'speaking';
                $payload['metadata'] = ['rubric' => SubjectiveQuestionWorkbookService::defaultRubric($editorSkill), 'variant' => 'academic'];
                if ($editorSkill === 'writing') $payload['metadata']['min_words'] = str_ends_with($currentPart?->code ?? '', '2') ? 250 : 150;
            }
        }

        return [
            'question' => $question,
            'payload' => $payload,
            'editorPreview' => $this->previewData($payload),
            'editorHistory' => $question ? $editor->history($question) : ['items' => [], 'total' => 0, 'next_page' => null],
            'editorExamLinks' => $question ? $editor->examLinks($question) : [],
            'editorCreator' => $question ? DB::table('users')->where('id', $question->learningItem?->created_by)->value('name') : auth()->user()?->name,
            'editorKnowledge' => LearningItem::whereIn('id', $payload['metadata']['knowledge_ids'] ?? [])->get(),
            'revision' => $question ? ContentRevision::find($question->draft_revision_id) : null,
            'groups' => DB::table('question_groups')->orderBy('title')->get(),
            'assets' => MediaAsset::whereIn('id', array_filter([$payload['metadata']['image_id'] ?? null, $payload['metadata']['audio_id'] ?? null]))->get(),
            'selectedStimulusId' => request()->integer('stimulus') ?: null,
            'parts' => $parts,
            'editorSkill' => $editorSkill,
            'editorTypeFamilies' => QuestionRegistry::TYPES,
            'questionTypes' => QuestionType::orderBy('id')->get(),
            'difficulties' => QuestionDifficulty::orderBy('sort_order')->get(),
            'stimuli' => LearningStimulus::query()->whereIn('stimulus_type', ['reading', 'audio'])->orderBy('stimulus_type')->orderBy('title')->get(),
            'tags' => $question ? DB::table('learning_item_tags')->join('tags', 'tags.id', '=', 'learning_item_tags.tag_id')->where('learning_item_id', $question->learning_item_id)->orderBy('tags.name')->pluck('tags.name')->implode(', ') : '',
        ];
    }

    private function questionLearningItemTypeId(): int
    {
        DB::table('learning_item_types')->insertOrIgnore(['code' => 'question', 'label' => 'Câu hỏi', 'sort_order' => 10]);

        return (int) DB::table('learning_item_types')->where('code', 'question')->value('id');
    }

    private function canonicalQuestionText(string $prompt): string
    {
        $normalized = (string) preg_replace('/\s+/', ' ', $prompt);

        return Str::substr($normalized, 0, 217).' #'.Str::uuid();
    }

    /** @param array<string, mixed> $attributes */
    private function validateAnswers(array $attributes, QuestionType $questionType): void
    {
        $options = collect($attributes['options'] ?? [])->map(fn ($option): string => trim((string) $option))->filter();
        $acceptedAnswers = collect($attributes['accepted_answers'] ?? [])->map(fn ($answer): string => trim((string) $answer))->filter()->values();

        if ($questionType->supports_options && ($options->count() < 2 || ! $options->has((string) ($attributes['correct_option'] ?? '')))) {
            throw ValidationException::withMessages(['options' => 'Câu hỏi dạng chọn đáp án cần ít nhất 2 phương án và một đáp án đúng.']);
        }

        if (! $questionType->supports_options && $acceptedAnswers->isEmpty() && $questionType->code !== 'essay') {
            throw ValidationException::withMessages(['accepted_answers' => 'Hãy thêm ít nhất một đáp án được chấp nhận.']);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function syncAnswers(QuestionBankItem $question, array $attributes, QuestionType $questionType): void
    {
        foreach ($question->options as $option) {
            if (! array_key_exists($option->option_key, $attributes['options'] ?? []) && ! DB::table('attempt_answer_options')->where('question_option_id', $option->id)->exists()) {
                $option->delete();
            }
        }
        $question->acceptedAnswers()->delete();

        if ($questionType->supports_options) {
            $correctOption = (string) ($attributes['correct_option'] ?? '');
            $options = collect($attributes['options'] ?? [])->map(fn ($option): string => trim((string) $option))->filter();
            $sortOrder = 0;
            foreach ($options as $key => $content) {
                $existing = $question->options()->where('option_key', (string) $key)->first();
                if ($existing && DB::table('attempt_answer_options')->where('question_option_id', $existing->id)->exists()) {
                    continue;
                }
                $question->options()->updateOrCreate(['option_key' => (string) $key], [
                    'question_bank_item_id' => $question->id,
                    'option_key' => (string) $key,
                    'content' => $content,
                    'sort_order' => $sortOrder++,
                    'is_correct' => in_array((string) $key, $attributes['correct_options'] ?? [$correctOption], true),
                ]);
            }

            return;
        }

        foreach (collect($attributes['accepted_answers'] ?? [])->map(fn ($answer): string => trim((string) $answer))->filter()->values() as $sortOrder => $answer) {
            QuestionAcceptedAnswer::create([
                'question_bank_item_id' => $question->id,
                'answer_text' => $answer,
                'normalized_answer' => Str::lower($answer),
                'sort_order' => $sortOrder,
            ]);
        }
    }

    private function syncTags(LearningItem $learningItem, string $input): void
    {
        DB::table('learning_item_tags')->where('learning_item_id', $learningItem->id)->delete();
        $tags = collect(explode(',', $input))->map(fn (string $tag): string => trim($tag))->filter()->unique();

        foreach ($tags as $tag) {
            $slug = Str::slug($tag);
            if ($slug === '') {
                continue;
            }

            DB::table('tags')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $tag, 'created_at' => now(), 'updated_at' => now()],
            );
            $tagId = DB::table('tags')->where('slug', $slug)->value('id');
            DB::table('learning_item_tags')->insertOrIgnore(['learning_item_id' => $learningItem->id, 'tag_id' => $tagId]);
        }
    }
}
