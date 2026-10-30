<?php

namespace VistiqAdmin\Http\Controllers\Admin\Assessment\Exams;

use VistiqAdmin\Http\Controllers\Admin\AdminPageController;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use VistiqAdmin\Http\Requests\Admin\Assessment\Exams\ExamGenerationRequest;
use VistiqAdmin\Http\Requests\Admin\Assessment\Exams\ExamUpsertRequest;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\ExamType;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Services\Assessment\Attempts\AttemptService;
use VistiqCore\Services\Assessment\Exams\ExamGenerationService;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;
use VistiqCore\Services\System\AuditService;

class ExamBuilderController extends AdminPageController
{
    public function index(Request $request): mixed
    {
        $query = ExamTemplate::with('examType')->where('status', '!=', 'archived');
        $types = ExamType::all();
        $typeCounts = ExamTemplate::selectRaw('exam_type_id, count(*) as total')->groupBy('exam_type_id')->pluck('total', 'exam_type_id');
        $creators = DB::table('users')->whereIn('id', ExamTemplate::whereNotNull('created_by')->select('created_by'))->pluck('name', 'id');
        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.$request->string('q').'%');
        }
        if ($types->contains('id', (int) $request->input('type'))) {
            $query->where('exam_type_id', $request->integer('type'));
        }
        if ($request->input('status') === 'approved') {
            $query->where(fn ($status) => $status->where('draft_status', 'approved')->orWhere('status', 'published'));
        } elseif (in_array($request->input('status'), ['draft', 'review'], true)) {
            $query->where('draft_status', $request->input('status'))->where('status', '!=', 'archived');
        }
        if (in_array($request->input('days'), ['7', '30', '90'], true)) {
            $query->where('updated_at', '>=', now()->subDays($request->integer('days')));
        }
        if ($creators->has($request->integer('creator'))) {
            $query->where('created_by', $request->integer('creator'));
        }
        if ($request->input('mode') === 'full') {
            $query->where('mode', 'full');
        }
        if (in_array($request->input('resource'), ['reading', 'audio'], true)) {
            $catalog = collect($this->questionCatalog(QuestionBankItem::with(['draftRevision', 'publishedRevision', 'learningItem', 'difficulty', 'stimulus', 'questionType'])->get()));
            $questionIds = $catalog->filter(fn ($question) => $request->input('resource') === 'reading' ? $question['passage'] !== null : $question['audio'])->pluck('id')->all();
            $matching = ExamTemplate::get(['id', 'draft_payload'])->filter(fn ($exam) => array_intersect($questionIds, array_merge([], ...array_column($exam->draft_payload['sections'] ?? [], 'question_ids'))))->pluck('id');
            $query->whereIn('id', $matching);
        }
        $sort = ['updated' => ['updated_at', 'desc'], 'created' => ['created_at', 'desc'], 'title' => ['title', 'asc']][$request->input('sort', 'updated')] ?? ['updated_at', 'desc'];
        $perPage = in_array($request->integer('per_page', 8), [8, 15, 30], true) ? $request->integer('per_page', 8) : 8;
        $exams = $query->orderBy(...$sort)->orderByDesc('id')->paginate($perPage)->withQueryString();
        $ids = $exams->getCollection()->flatMap(fn ($exam) => array_merge([], ...array_column($exam->draft_payload['sections'] ?? [], 'question_ids')))->unique();
        $questions = $this->questionCatalog(QuestionBankItem::with(['draftRevision', 'learningItem', 'difficulty', 'publishedRevision', 'stimulus', 'questionType'])->whereIn('id', $ids)->get());
        $parts = ExamPart::all()->keyBy('id');
        $summaries = $exams->getCollection()->mapWithKeys(fn ($exam) => [$exam->id => $this->summary($exam, $questions, $parts, $creators)]);

        return $this->page('Đề thi', 'pages.admin.assessment.exams.workspace.index', compact('exams', 'types', 'typeCounts', 'creators', 'summaries'));
    }

    public function create(): mixed
    {
        return $this->editor(new ExamTemplate(['mode' => 'mini', 'draft_payload' => ['sections' => [], 'policies' => ExamPublishingService::POLICIES]]));
    }

    public function edit(ExamTemplate $exam): mixed
    {
        return $this->editor($exam);
    }

    private function editor(ExamTemplate $exam): mixed
    {
        $questionCatalog = $this->questionCatalog(QuestionBankItem::with(['draftRevision', 'learningItem', 'difficulty', 'publishedRevision', 'stimulus', 'questionType'])->orderBy('id')->get());
        $creatorName = DB::table('users')->where('id', $exam->created_by ?? auth()->id())->value('name');

        return $this->page('Soạn đề thi', 'pages.admin.assessment.exams.workspace.editor', ['exam' => $exam, 'defaultPolicies' => ExamPublishingService::POLICIES, 'types' => ExamType::all(), 'parts' => ExamPart::with(['examType', 'skill'])->get(), 'scales' => DB::table('score_scales')->get(), 'structureTemplates' => app(ExamGenerationService::class)->catalog(), 'questionCatalog' => $questionCatalog, 'creatorName' => $creatorName]);
    }

    private function questionCatalog($questions): array
    {
        if ($questions->isEmpty()) {
            return [];
        }
        $difficultyCodes = DB::table('question_difficulties')->pluck('code', 'id');
        $revisionFor = fn ($question) => $question->draftRevision?->status === 'approved'
            ? $question->draftRevision
            : ($question->contentStatus() === 'published' ? $question->publishedRevision : ($question->draftRevision ?? $question->publishedRevision));
        $groupIds = $questions->map(fn ($question) => $revisionFor($question)?->payload['group_id'] ?? $question->question_group_id)->filter()->unique();
        $groups = DB::table('question_groups')->whereIn('id', $groupIds)->get()->keyBy('id');
        $resourceIds = $questions->flatMap(fn ($question) => $revisionFor($question)?->payload['stimulus_ids'] ?? [$question->learning_stimulus_id])->merge($groups->flatMap(fn ($group) => json_decode($group->stimulus_ids ?? '[]', true) ?? []))->filter()->unique();
        $stimuli = LearningStimulus::whereIn('id', $resourceIds)->get()->keyBy('id');
        $audioIds = $questions->flatMap(function ($question) use ($groups, $stimuli, $revisionFor) {
            $payload = $revisionFor($question)?->payload ?? [];
            $group = $groups->get($payload['group_id'] ?? $question->question_group_id);
            $metadata = json_decode($group?->metadata ?? '{}', true) ?: [];
            $stimulusIds = collect(array_merge($payload['stimulus_ids'] ?? [], json_decode($group?->stimulus_ids ?? '[]', true) ?: []))->map(fn ($id) => (int) $id)->unique();

            return collect([$payload['metadata']['audio_id'] ?? null, $metadata['audio_id'] ?? null])->merge($stimulusIds->map(fn ($id) => $stimuli->get($id))->filter(fn ($stimulus) => $stimulus?->stimulus_type === 'audio')->map(fn ($stimulus) => $stimulus->metadata['asset_id'] ?? null));
        })->filter()->unique();
        $audioAssets = MediaAsset::whereIn('id', $audioIds)->get()->keyBy('id');

        return $questions->map(function ($question) use ($difficultyCodes, $groups, $stimuli, $audioAssets, $revisionFor) {
            $revision = $revisionFor($question);
            $payload = $revision?->payload ?? [];
            $payload['metadata'] ??= $question->metadata ?? [];
            $groupId = $payload['group_id'] ?? $question->question_group_id;
            $group = $groups->get($groupId);
            $ids = array_unique(array_filter(array_merge($payload['stimulus_ids'] ?? [$question->learning_stimulus_id], json_decode($group?->stimulus_ids ?? '[]', true) ?? [])));
            $resources = collect($ids)->map(fn ($id) => $stimuli->get($id))->filter();
            $passage = $resources->firstWhere('stimulus_type', 'reading');
            $audio = $resources->firstWhere('stimulus_type', 'audio');
            $groupMetadata = json_decode($group?->metadata ?? '{}', true) ?: [];
            $audioAsset = $audioAssets->get((int) ($payload['metadata']['audio_id'] ?? $groupMetadata['audio_id'] ?? $audio?->metadata['asset_id'] ?? 0));
            $options = collect($payload['options'] ?? [])->map(function ($option, $key) {
                if (is_array($option)) {
                    return ['key' => $option['key'] ?? $key, 'text' => strip_tags($option['content'] ?? $option['text'] ?? '')];
                }

                return ['key' => $key, 'text' => strip_tags((string) $option)];
            })->values()->all();
            $tags = collect($payload['metadata']['tags'] ?? $payload['tags'] ?? [])->map(fn ($tag) => is_array($tag) ? ($tag['name'] ?? '') : (string) $tag)->filter()->values()->all();

            $publishedRevision = $question->publishedRevision;
            $isPublished = $publishedRevision
                ? $publishedRevision->status === 'published' && $publishedRevision->kind === 'question' && (int) $publishedRevision->entity_id === (int) $question->id
                : $question->contentStatus() === 'published';
            $isApproved = $question->draftRevision
                && $question->draftRevision->status === 'approved'
                && $question->draftRevision->kind === 'question'
                && (int) $question->draftRevision->entity_id === (int) $question->id;
            $status = $isApproved ? 'approved' : ($isPublished ? 'published' : ($question->contentStatus() ?? 'draft'));

            return ['id' => $question->id, 'part' => $payload['exam_part_id'] ?? $question->exam_part_id, 'prompt' => strip_tags($payload['prompt'] ?? $question->prompt), 'type' => $payload['type'] ?? $question->questionType?->code, 'options' => $options, 'tags' => $tags, 'status' => $status, 'has_approved_content' => $isApproved || $isPublished, 'difficulty' => $difficultyCodes[$payload['question_difficulty_id'] ?? $question->question_difficulty_id] ?? 'medium', 'units' => $revision ? app(\VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry::class)->units($payload) : 1, 'group' => $groupId, 'passage' => $passage ? ['id' => $passage->id, 'title' => $passage->title, 'body' => strip_tags($passage->body)] : null, 'audio' => $audio !== null || $audioAsset !== null, 'audio_title' => $audioAsset?->title ?? $audio?->title, 'audio_url' => $audioAsset ? route('admin.media.waveform', $audioAsset) : null, 'edit_url' => route('admin.questions.edit', $question)];
        })->all();
    }

    private function summary(ExamTemplate $exam, array $questions, $parts, $creators): array
    {
        $questionMap = collect($questions)->keyBy('id');
        $sections = collect($exam->draft_payload['sections'] ?? [])->map(function ($section) use ($questionMap, $parts) {
            $part = $parts->get($section['exam_part_id']);
            return ['name' => $part?->name ?? 'Phần thi', 'code' => $part?->code ?? '', 'count' => collect($section['question_ids'] ?? [])->sum(fn ($id) => $questionMap->get($id)['units'] ?? 1)];
        })->all();
        $selected = collect($exam->draft_payload['sections'] ?? [])->flatMap(fn ($section) => $section['question_ids'] ?? [])->map(fn ($id) => $questionMap->get($id))->filter();
        $status = $exam->status === 'archived' ? 'archived' : (in_array($exam->status, ['approved', 'published'], true) ? 'approved' : ($exam->draft_status ?? 'draft'));

        return ['code' => strtoupper($exam->examType?->code ?? 'EXAM').'-'.str_pad($exam->id, 4, '0', STR_PAD_LEFT), 'status' => $status, 'status_label' => ['draft' => 'Bản nháp', 'review' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'archived' => 'Bản nháp'][$status] ?? $status, 'creator' => $creators->get($exam->created_by, '—'), 'sections' => $sections, 'count' => array_sum(array_column($sections, 'count')), 'duration' => $exam->draft_payload['policies']['duration_minutes'] ?? 30, 'audio' => $selected->where('audio', true)->count(), 'passages' => $selected->pluck('passage.id')->filter()->unique()->count(), 'generation' => ! empty($exam->draft_payload['generation'])];
    }

    public function generate(ExamGenerationRequest $request, ExamGenerationService $service): mixed
    {
        return response()->json($service->generate($request->validated()));
    }

    public function previewDraft(ExamUpsertRequest $request, ExamPublishingService $service, ExamGenerationService $generation): mixed
    {
        $data = $request->validated();
        $generation->validateSelection($data);
        $data['exam_type_id'] = (int) $data['exam_type_id'];
        $exam = new ExamTemplate($data);
        $exam->draft_payload = ['sections' => $data['sections'], 'policies' => $data['policies'], 'generation' => $data['generation'] ?? null];

        return $this->preview($exam, $service);
    }

    public function store(ExamUpsertRequest $request, ExamPublishingService $service): mixed
    {
        $exam = DB::transaction(function () use ($request, $service) {
            $modeId = DB::table('practice_modes')->orderBy('id')->value('id');
            $exam = ExamTemplate::create(['title' => $request->validated('title'), 'slug' => Str::slug($request->validated('title')).'-'.Str::random(8), 'exam_type_id' => $request->validated('exam_type_id'), 'practice_mode_id' => $modeId, 'created_by' => auth()->id(), 'status' => 'draft']);

            return $service->save($exam, $request->validated(), 0);
        });

        return redirect()->route('admin.exams.edit', $exam)->with('status', 'Đã tạo đề bản nháp.');
    }

    public function update(ExamUpsertRequest $request, ExamTemplate $exam, ExamPublishingService $service): mixed
    {
        $exam = $service->save($exam, $request->validated(), $request->validated('revision_counter'));

        return $request->expectsJson() ? response()->json(['revision_counter' => $exam->revision_counter]) : back()->with('status', 'Đã lưu đề.');
    }

    public function destroy(ExamTemplate $exam, AuditService $audit): mixed
    {
        Gate::authorize('content.edit');
        $before = ['title' => $exam->title, 'status' => $exam->status, 'attempts' => $exam->attempts()->count()];
        $exam->delete();
        $audit->record('exam.delete', 'exam', $exam->id, $before, ['soft_deleted' => true]);

        return redirect()->route('admin.exams.index')->with('status', 'Đã xóa mềm đề “'.$exam->title.'”. Đề đã ẩn khỏi kho học viên; lịch sử làm bài được giữ nguyên.');
    }

    public function deleted(): mixed
    {
        return $this->page('Đề thi đã xóa', 'pages.admin.assessment.exams.workspace.deleted', [
            'exams' => ExamTemplate::onlyTrashed()->with('examType')->orderByDesc('deleted_at')->paginate(20),
        ]);
    }

    public function restore(int $exam, AuditService $audit): mixed
    {
        Gate::authorize('content.edit');
        $restored = DB::transaction(function () use ($exam, $audit): ExamTemplate {
            $item = ExamTemplate::withTrashed()->lockForUpdate()->findOrFail($exam);
            abort_unless($item->trashed(), 404);
            $before = ['deleted_at' => $item->deleted_at?->toIso8601String(), 'status' => $item->status];
            $item->restore();
            $audit->record('exam.restore', 'exam', $item->id, $before, ['soft_deleted' => false]);

            return $item;
        });

        return redirect()->route('admin.exams.index')->with('status', 'Đã khôi phục đề “'.$restored->title.'”. Đề sẽ hiển thị lại cho học viên nếu đang ở trạng thái được duyệt.');
    }

    public function validation(ExamTemplate $exam, ExamPublishingService $service): mixed
    {
        return response()->json(['errors' => $service->problems($service->assemble($exam, true))]);
    }

    public function preview(ExamTemplate $exam, ExamPublishingService $service): mixed
    {
        $snapshot = $service->assemble($exam, true);
        $warnings = $service->problems($snapshot);
        $snapshot['policies']['strict_audio'] = false;
        $snapshot = app(AttemptService::class)->publicSnapshot($snapshot);

        return view('exam.runner', ['preview' => true, 'attempt' => null, 'snapshot' => $snapshot, 'endpoints' => [], 'warnings' => $warnings]);
    }
}
