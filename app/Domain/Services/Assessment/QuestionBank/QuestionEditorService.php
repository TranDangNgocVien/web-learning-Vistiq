<?php

namespace VistiqCore\Services\Assessment\QuestionBank;

use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\LearningItem;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Models\User;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;

class QuestionEditorService
{
    public function catalog(string $kind, string $search, ?int $partId = null): array
    {
        if ($kind === 'tag') {
            return DB::table('tags')->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))->orderBy('name')->limit(30)->get(['id', 'name'])->toArray();
        }
        if ($kind === 'knowledge') {
            return LearningItem::query()->join(DB::source('learning_item_types'), 'learning_item_types.id', '=', 'learning_items.learning_item_type_id')
                ->where('learning_items.status', '!=', 'archived')->where('learning_item_types.code', '!=', 'question')
                ->when($search !== '', fn ($q) => $q->where('canonical_text', 'like', '%'.$search.'%'))
                ->orderBy('canonical_text')->limit(30)->get(['learning_items.id', 'canonical_text', 'learning_item_types.label'])
                ->map(fn ($item) => ['id' => $item->id, 'name' => $item->canonical_text, 'detail' => $item->label])->all();
        }
        if (in_array($kind, ['image', 'audio'], true)) {
            return MediaAsset::where('mime_type', 'like', $kind.'/%')->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
                ->latest()->limit(30)->get()->map(fn ($asset) => ['id' => $asset->id, 'name' => $asset->title ?: basename($asset->path), 'detail' => round($asset->size / 1048576, 2).' MB', 'url' => route('admin.media.show', $asset)])->all();
        }
        if ($kind === 'stimulus') {
            return LearningStimulus::whereIn('stimulus_type', ['reading', 'audio'])->where('status', '!=', 'archived')
                ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))->latest()->limit(30)->get()
                ->map(fn ($item) => ['id' => $item->id, 'name' => $item->title ?: 'Tài nguyên #'.$item->id, 'detail' => $item->stimulus_type === 'reading' ? 'Bài đọc' : 'Bài nghe', 'kind' => $item->stimulus_type])->all();
        }
        $part = ExamPart::findOrFail($partId);

        return ExamTemplate::where('exam_type_id', $part->exam_type_id)->where('status', '!=', 'archived')
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))->latest()->limit(30)->get()
            ->map(fn ($exam) => ['id' => $exam->id, 'name' => $exam->title, 'detail' => $exam->draft_status ?? 'draft', 'revision_counter' => $exam->revision_counter])->all();
    }

    public function history(QuestionBankItem $question, int $page = 1): array
    {
        $revisions = ContentRevision::where(['kind' => 'question', 'entity_id' => $question->id])->orderByDesc('number')->paginate(10, ['*'], 'page', $page);
        $previous = ContentRevision::where(['kind' => 'question', 'entity_id' => $question->id])->whereIn('number', $revisions->pluck('number')->map(fn ($number) => $number - 1))->get()->keyBy('number');
        $actors = User::whereIn('id', $revisions->pluck('created_by')->filter()->unique())->pluck('name', 'id');
        $labels = ['prompt' => 'nội dung câu hỏi', 'options' => 'phương án', 'correct_options' => 'đáp án đúng', 'accepted_answers' => 'đáp án chấp nhận', 'explanation' => 'giải thích', 'exam_part_id' => 'phần / kỹ năng', 'type' => 'dạng câu hỏi', 'question_difficulty_id' => 'mức độ', 'metadata' => 'tài nguyên / kiến thức', 'stimulus_ids' => 'bài đọc / bài nghe', 'tags' => 'thẻ'];

        return ['items' => $revisions->map(function ($revision) use ($previous, $actors, $labels): array {
            $before = $previous->get($revision->number - 1)?->payload;
            $changed = $before === null ? [] : array_keys(array_filter($labels, fn ($label, $field) => ($before[$field] ?? null) !== ($revision->payload[$field] ?? null), ARRAY_FILTER_USE_BOTH));

            return ['id' => $revision->id, 'number' => $revision->number, 'actor' => $actors[$revision->created_by] ?? 'Không xác định', 'summary' => $before === null ? 'Tạo bản nháp' : ($changed === [] ? 'Lưu bản nháp' : 'Cập nhật '.implode(', ', array_map(fn ($field) => $labels[$field], $changed))), 'status' => $revision->status, 'created_at' => $revision->created_at->toIso8601String(), 'time' => $revision->created_at->locale('vi')->diffForHumans()];
        })->all(), 'total' => $revisions->total(), 'next_page' => $revisions->hasMorePages() ? $page + 1 : null];
    }

    public function examLinks(QuestionBankItem $question): array
    {
        $links = [];
        $legacy = DB::table('exam_section_questions')->join('exam_sections', 'exam_sections.id', '=', 'exam_section_questions.exam_section_id')->where('question_bank_item_id', $question->id)->pluck('exam_template_id')->all();
        foreach (ExamTemplate::with('publishedRevision')->get() as $exam) {
            $position = 0;
            $draftPosition = null;
            foreach ($exam->draft_payload['sections'] ?? [] as $section) {
                foreach ($section['question_ids'] ?? [] as $id) {
                    $position++;
                    if ((int) $id === $question->id) {
                        $draftPosition = $position;
                    }
                }
            }
            $publishedItems = collect($exam->publishedRevision?->payload['items'] ?? []);
            $publishedPosition = $publishedItems->search(fn ($item) => (int) ($item['id'] ?? 0) === $question->id);
            $published = $publishedPosition !== false || in_array($exam->id, $legacy, true);
            if ($draftPosition !== null || $published) {
                $links[] = ['id' => $exam->id, 'title' => $exam->title, 'position' => $draftPosition ?? ($publishedPosition !== false ? $publishedPosition + 1 : null), 'draft' => $draftPosition !== null, 'published' => $published, 'revision_counter' => $exam->revision_counter, 'url' => route('admin.exams.edit', $exam)];
            }
        }

        return $links;
    }

    public function changeExamLink(QuestionBankItem $question, ExamTemplate $exam, bool $attach, int $expected): array
    {
        Gate::authorize('edit', $question);
        Gate::authorize('edit', $exam);
        DB::transaction(function () use ($question, $exam, $attach, $expected): void {
            $exam = ExamTemplate::lockForUpdate()->findOrFail($exam->id);
            abort_if($exam->revision_counter !== $expected, 409, 'Đề đã thay đổi. Hãy tải lại danh sách liên kết.');
            abort_if($exam->status === 'archived', 409, 'Đề đã lưu trữ.');
            $payload = app(ContentRevisionService::class)->payload('question', $question);
            $part = ExamPart::findOrFail($payload['exam_part_id']);
            if ($attach && ($exam->exam_type_id !== $part->exam_type_id || $question->contentStatus() === 'archived')) {
                throw ValidationException::withMessages(['exam' => 'Câu hỏi không phù hợp kỳ thi hoặc đã lưu trữ.']);
            }
            $draft = $exam->draft_payload ?? [];
            $sections = $draft['sections'] ?? [];
            // Legacy exams need their existing content copied into the draft first.
            if ($sections === [] && $exam->publishedRevision) {
                $snapshot = $exam->publishedRevision->payload;
                foreach ($snapshot['sections'] ?? [] as $section) {
                    $sections[] = ['exam_part_id' => $section['exam_part_id'], 'question_ids' => collect($snapshot['items'] ?? [])->whereIn('key', $section['item_keys'] ?? [])->pluck('id')->all(), 'duration_minutes' => $section['duration_minutes'] ?? ExamPublishingService::POLICIES['duration_minutes']];
                }
            }
            $alreadyLinked = collect($sections)->flatMap(fn ($section) => $section['question_ids'] ?? [])->contains(fn ($id) => (int) $id === $question->id);
            if ($attach && $alreadyLinked) {
                return;
            }
            if (! $attach && ! $alreadyLinked) {
                abort(409, 'Câu hỏi chỉ có trong bản đã xuất bản; không có liên kết nháp để gỡ.');
            }
            $target = null;
            foreach ($sections as $index => &$section) {
                if ((int) $section['exam_part_id'] === $part->id) {
                    $target = $index;
                }
                if (! $attach) {
                    $section['question_ids'] = array_values(array_filter($section['question_ids'] ?? [], fn ($id) => (int) $id !== $question->id));
                }
            }
            unset($section);
            if ($attach) {
                if ($target === null) {
                    $target = count($sections);
                    $sections[] = ['exam_part_id' => $part->id, 'question_ids' => [], 'duration_minutes' => $draft['policies']['duration_minutes'] ?? ExamPublishingService::POLICIES['duration_minutes']];
                }
                $sections[$target]['question_ids'][] = $question->id;
            }
            if (count($sections) > 20 || collect($sections)->contains(fn ($section) => count($section['question_ids'] ?? []) > 200)) {
                throw ValidationException::withMessages(['exam' => 'Đề vượt giới hạn phần hoặc số câu hỏi.']);
            }
            app(ExamPublishingService::class)->save($exam, ['exam_type_id' => $exam->exam_type_id, 'mode' => $exam->mode, 'variant' => $exam->variant, 'sections' => $sections, 'policies' => $draft['policies'] ?? ExamPublishingService::POLICIES, 'generation' => $draft['generation'] ?? null], $expected);
        });

        return $this->examLinks($question);
    }
}
