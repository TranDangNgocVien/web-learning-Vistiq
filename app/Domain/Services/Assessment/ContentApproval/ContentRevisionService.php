<?php

namespace VistiqCore\Services\Assessment\ContentApproval;

use Illuminate\Database\Eloquent\Model;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\LearningItem;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;
use VistiqCore\Services\System\AuditService;

class ContentRevisionService
{
    public function __construct(private AuditService $audit, private QuestionRegistry $registry) {}

    public function root(string $kind, int $id): Model
    {
        return match ($kind) {
            'question' => QuestionBankItem::findOrFail($id), 'stimulus' => LearningStimulus::findOrFail($id), 'knowledge' => LearningItem::findOrFail($id), default => abort(404)
        };
    }

    public function payload(string $kind, Model $root, bool $preferDraft = true): array
    {
        $revision = ContentRevision::find($preferDraft ? ($root->draft_revision_id ?? $root->published_revision_id) : $root->published_revision_id);
        if ($revision) {
            return $revision->payload;
        }
        if ($kind === 'question') {
            $root->load(['options', 'acceptedAnswers', 'examPart.examType', 'examPart.skill', 'learningItem']);

            return ['schema_version' => 1, 'id' => $root->id, 'exam_part_id' => $root->exam_part_id, 'question_difficulty_id' => $root->question_difficulty_id, 'exam_type' => $root->examPart?->examType?->code, 'part' => $root->examPart?->code, 'skill' => $root->examPart?->skill?->code, 'type' => $root->questionType->code, 'prompt' => $root->prompt, 'explanation' => $root->explanation, 'options' => $root->options->pluck('content', 'option_key')->all(), 'correct_options' => $root->options->where('is_correct', true)->pluck('option_key')->all(), 'accepted_answers' => $root->acceptedAnswers->pluck('answer_text')->all(), 'metadata' => $root->metadata ?? [], 'stimulus_ids' => array_values(array_filter([$root->learning_stimulus_id])), 'group_id' => $root->question_group_id, 'group_order' => $root->group_order];
        }
        if ($kind === 'stimulus') {
            return ['schema_version' => 1, 'id' => $root->id, 'title' => $root->title, 'stimulus_type' => $root->stimulus_type, 'body' => $this->registry->sanitize($root->body ?? ''), 'transcript' => $root->transcript, 'asset_path' => $root->asset_path, 'duration_seconds' => $root->duration_seconds, 'accent' => $root->accent, 'metadata' => $root->metadata ?? []];
        }
        $meaning = DB::table('learning_item_meanings')->where('learning_item_id', $root->id)->orderBy('sense_number')->first();
        $entry = DB::table('dictionary_entries')->where('learning_item_id', $root->id)->first();
        $grammar = DB::table('grammar_topics')->where('learning_item_id', $root->id)->first();
        $type = DB::table('learning_item_types')->where('id', $root->learning_item_type_id)->value('code');
        $payload = array_replace($root->metadata ?? [], [
            'schema_version' => 1,
            'id' => $root->id,
            'type' => $type,
            'english' => $root->canonical_text,
            'meaning_vi' => $meaning?->translation,
            'definition_en' => $meaning?->definition,
            'part_of_speech_id' => $meaning?->part_of_speech_id,
            'proficiency_level_id' => $root->proficiency_level_id,
            'ipa' => $entry?->phonetic,
            'content' => $grammar?->content,
            'grammar_category_id' => $grammar?->grammar_category_id,
            'summary' => $grammar?->summary,
            'examples' => $meaning ? DB::table('learning_item_meaning_examples')->where('learning_item_meaning_id', $meaning->id)->orderBy('sort_order')->get(['english', 'vietnamese'])->map(fn ($row) => (array) $row)->all() : [],
            'tags' => DB::table('learning_item_tags')->join('tags', 'tags.id', '=', 'learning_item_tags.tag_id')->where('learning_item_tags.learning_item_id', $root->id)->orderBy('tags.name')->pluck('tags.name')->implode(', '),
            'relations' => DB::table('learning_item_relations')->where('source_learning_item_id', $root->id)->orderBy('id')->get(['target_learning_item_id as id', 'relation_type as type'])->map(fn ($row) => (array) $row)->all(),
        ]);

        if ($type === 'collocation') {
            $collocation = DB::table('collocations')->where('learning_item_id', $root->id)->first();
            $payload['grammatical_pattern'] = $collocation?->grammatical_pattern;
            $payload['dictionary_entry_ids'] = $collocation ? DB::table('collocation_dictionary_entries')->where('collocation_id', $collocation->id)->orderBy('dictionary_entry_id')->pluck('dictionary_entry_id')->all() : [];
        } elseif ($type === 'phrasal_verb') {
            $phrasalVerb = DB::table('phrasal_verbs')->where('learning_item_id', $root->id)->first();
            $payload['base_dictionary_entry_id'] = $phrasalVerb?->base_dictionary_entry_id;
            $payload['particle'] = $phrasalVerb?->particle;
        } elseif ($type === 'idiom') {
            $idiom = DB::table('idioms')->where('learning_item_id', $root->id)->first();
            $payload['register'] = $idiom?->register;
            $payload['usage_note'] = $idiom?->usage_note;
        } elseif ($type === 'grammar' && $grammar) {
            $payload['grammar_rules'] = DB::table('grammar_rules')->where('grammar_topic_id', $grammar->id)->orderBy('sort_order')->get(['title', 'formula', 'explanation'])->map(fn ($row) => (array) $row)->all();
        }

        return $payload;
    }

    /**
     * Resolve the current approved revision for use in a published exam, falling
     * back to the immutable published revision when there is no approved draft.
     */
    public function approvedOrPublishedRevision(string $kind, Model $root): ?ContentRevision
    {
        $identity = ['kind' => $kind, 'entity_id' => $root->id];
        $current = $root->draft_revision_id
            ? ContentRevision::whereKey($root->draft_revision_id)->where($identity)->first()
            : null;

        if ($current?->status === 'approved') {
            return $current;
        }

        $published = $root->published_revision_id
            ? ContentRevision::whereKey($root->published_revision_id)->where($identity)->where('status', 'published')->first()
            : null;

        return $published;
    }

    public function save(string $kind, Model $root, array $payload, ?int $expected): ContentRevision
    {
        Gate::authorize('edit', $root);

        return DB::transaction(function () use ($kind, $root, $payload, $expected): ContentRevision {
            $root = $root->newQuery()->lockForUpdate()->findOrFail($root->id);
            abort_if($root->revision_counter > 0 && $expected !== $root->revision_counter, 409, 'Nội dung đã thay đổi. Hãy tải lại bản mới nhất.');
            $liveStatus = $kind === 'question' ? $root->contentStatus() : $root->status;
            if (! $root->published_revision_id && $liveStatus === 'published') {
                $this->published($kind, $root);
                $root->refresh();
            }
            foreach (['prompt', 'body', 'content', 'explanation', 'explanation_en', 'explanation_vi'] as $field) {
                if (isset($payload[$field])) {
                    $payload[$field] = $this->registry->sanitize($payload[$field]);
                }
            }
            $payload['schema_version'] = 1;
            $payload['id'] = $root->id;
            $revision = ContentRevision::create(['kind' => $kind, 'entity_id' => $root->id, 'number' => $root->revision_counter + 1, 'status' => 'draft', 'payload' => $payload, 'created_by' => auth()->id()]);
            $root->forceFill(['draft_revision_id' => $revision->id, 'revision_counter' => $revision->number])->save();
            $this->audit->record('content.save', $kind, $root->id, [], ['revision' => $revision->number]);

            return $revision;
        });
    }

    public function transition(string $kind, Model $root, string $action, ?string $note = null): ContentRevision
    {
        return DB::transaction(function () use ($kind, $root, $action, $note): ContentRevision {
            $root = $root->newQuery()->lockForUpdate()->findOrFail($root->id);
            $revision = ContentRevision::findOrFail($action === 'archive' ? $root->published_revision_id : ($root->draft_revision_id ?? $root->published_revision_id));
            $ability = match ($action) {
                'review' => 'content.edit','approve','reject' => 'content.review','publish','archive' => 'content.publish',default => abort(422)
            };
            Gate::authorize(match ($action) {
                'review' => 'edit','approve','reject' => 'review',default => 'publish'
            }, $root);
            $note = $note === null ? null : trim($note);
            if (mb_strlen($note ?? '') > 2000) {
                throw ValidationException::withMessages(['note' => 'Lý do không được dài quá 2.000 ký tự.']);
            }
            $selfReview = $kind === 'question'
                && $action === 'approve'
                && (int) $revision->created_by === (int) auth()->id()
                && auth()->user()?->hasRole('super-admin');
            abort_if($kind === 'question' && $action === 'approve' && (int) $revision->created_by === (int) auth()->id() && ! $selfReview, 403, 'Tác giả cần reviewer khác duyệt revision này.');
            $liveStatus = $kind === 'question' ? $root->contentStatus() : $root->status;
            abort_if($liveStatus === 'archived' && $action !== 'archive', 409, 'Nội dung đã lưu trữ.');
            $allowed = ['review' => ['draft'], 'approve' => ['review'], 'reject' => ['review'], 'publish' => ['approved'], 'archive' => ['published']];
            abort_unless(in_array($revision->status, $allowed[$action], true), 409, 'Trạng thái hiện tại không cho phép thao tác này.');
            if ($action === 'reject' && trim($note ?? '') === '') {
                throw ValidationException::withMessages(['note' => 'Nhập lý do từ chối.']);
            }
            if (in_array($action, ['review', 'publish'], true) || ($kind === 'question' && $action === 'approve')) {
                $this->validate($kind, $revision->payload);
            }
            if ($kind === 'question' && $action === 'approve') {
                $blockers = app(ContentReviewService::class)->preview('question', $root->id, true)['blockers'];
                if ($blockers) {
                    throw ValidationException::withMessages(['content' => $blockers]);
                }
            }
            $before = ['status' => $revision->status, 'revision' => $revision->number];
            if ($action === 'archive') {
                $this->mirrorStatus($kind, $root, 'archived');
            } else {
                $status = match ($action) {
                    'review' => 'review','approve' => 'approved','reject' => 'draft','publish' => 'published'
                };
                $revision->update(['status' => $status, 'review_note' => $note, 'reviewed_by' => in_array($action, ['approve', 'reject'], true) ? auth()->id() : $revision->reviewed_by]);
                if ($action === 'publish') {
                    $root->forceFill(['published_revision_id' => $revision->id])->save();
                    $this->mirrorStatus($kind, $root, 'published');
                    if ($kind === 'knowledge') {
                        $this->publishKnowledge($root, $revision->payload);
                    }
                    if ($kind === 'stimulus') {
                        $root->forceFill(array_intersect_key($revision->payload, array_flip(['title', 'body', 'transcript', 'asset_path', 'duration_seconds', 'accent', 'metadata'])))->save();
                    }
                } elseif (! $root->published_revision_id) {
                    $this->mirrorStatus($kind, $root, $status);
                }
            }
            $after = ['status' => $action === 'archive' ? 'archived' : $revision->status, 'revision' => $revision->number, 'note' => $note];
            if ($selfReview) {
                $after['self_review'] = true;
            }
            $this->audit->record('content.'.$action, $kind, $root->id, $before, $after);

            return $revision;
        });
    }

    public function published(string $kind, Model $root): ContentRevision
    {
        if ($root->published_revision_id) {
            return ContentRevision::findOrFail($root->published_revision_id);
        }
        $status = $kind === 'question' ? $root->contentStatus() : $root->status;
        abort_unless($status === 'published', 422, 'Nội dung chưa xuất bản.');

        return DB::transaction(function () use ($kind, $root): ContentRevision {
            $root = $root->newQuery()->lockForUpdate()->findOrFail($root->id);
            if ($root->published_revision_id) {
                return ContentRevision::findOrFail($root->published_revision_id);
            }
            $payload = $this->payload($kind, $root, false);
            $revision = ContentRevision::create(['kind' => $kind, 'entity_id' => $root->id, 'number' => $root->revision_counter + 1, 'status' => 'published', 'payload' => $payload, 'created_by' => auth()->id()]);
            $root->forceFill(['published_revision_id' => $revision->id, 'revision_counter' => $revision->number])->save();

            return $revision;
        });
    }

    public function validate(string $kind, array $payload): void
    {
        if ($kind === 'question') {
            $this->registry->validate($payload);
        } elseif ($kind === 'stimulus' && ($payload['stimulus_type'] ?? '') === 'reading' && trim(strip_tags($payload['body'] ?? '')) === '') {
            throw ValidationException::withMessages(['body' => 'Passage cần nội dung.']);
        } elseif ($kind === 'stimulus' && ($payload['stimulus_type'] ?? '') === 'audio' && empty($payload['metadata']['asset_id']) && empty($payload['asset_path'])) {
            throw ValidationException::withMessages(['media' => 'Audio cần file.']);
        } elseif ($kind === 'knowledge' && ($payload['type'] ?? '') === 'phrasal_verb' && (empty($payload['base_dictionary_entry_id']) || blank($payload['particle'] ?? null))) {
            throw ValidationException::withMessages(['particle' => 'Phrasal Verb cần base dictionary entry và particle.']);
        } elseif ($kind === 'knowledge' && (trim($payload['english'] ?? '') === '' || (($payload['type'] ?? '') !== 'grammar' && trim($payload['meaning_vi'] ?? '') === ''))) {
            throw ValidationException::withMessages(['english' => 'Nhập nội dung tiếng Anh và nghĩa Việt.']);
        }
    }

    public function delete(string $kind, Model $root, ?array $questionIdsUsedInDraftExams = null): void
    {
        Gate::authorize('edit', $root);
        DB::transaction(function () use ($kind, $root, $questionIdsUsedInDraftExams): void {
            $root = $root->newQuery()->lockForUpdate()->findOrFail($root->id);
            $status = $kind === 'question' ? $root->contentStatus() : $root->status;
            if ($kind !== 'question') {
                abort_if($status !== 'draft' || ($root->draft_revision_id && ContentRevision::find($root->draft_revision_id)?->status !== 'draft'), 409, 'Chỉ xóa nội dung bản nháp chưa sử dụng.');
            }

            abort_if($root->published_revision_id || ContentRevision::where(['kind' => $kind, 'entity_id' => $root->id, 'status' => 'published'])->exists(), 409, 'Nội dung đã xuất bản chỉ được archive.');
            if ($kind === 'question') {
                if ($questionIdsUsedInDraftExams === null) {
                    foreach (ExamTemplate::whereNotNull('draft_payload')->cursor() as $exam) {
                        abort_if(collect($exam->draft_payload['sections'] ?? [])->contains(fn ($section) => in_array($root->id, $section['question_ids'] ?? [])), 409, 'Câu hỏi đang được dùng trong đề bản nháp.');
                    }
                } else {
                    abort_if(in_array($root->id, $questionIdsUsedInDraftExams, true), 409, 'Câu hỏi đang được dùng trong đề bản nháp.');
                }

                abort_if(DB::table('exam_section_questions')->where('question_bank_item_id', $root->id)->exists(), 409, 'Câu hỏi đang được sử dụng.');
            }
            if ($kind === 'stimulus') {
                $linkedGroup = DB::table('question_groups')->whereJsonContains('stimulus_ids', $root->id)
                    ->orWhereJsonContains('stimulus_ids', (string) $root->id)->exists();
                $linkedRevision = ContentRevision::where('kind', 'question')->where(function ($query) use ($root): void {
                    $query->whereJsonContains('payload->stimulus_ids', $root->id)
                        ->orWhereJsonContains('payload->stimulus_ids', (string) $root->id);
                })->exists();
                abort_if($linkedGroup || $linkedRevision, 409, 'Tài nguyên đang liên kết với nhóm hoặc revision.');
                abort_if(QuestionBankItem::where('learning_stimulus_id', $root->id)->exists(), 409, 'Tài nguyên đang được sử dụng.');
            }
            $root->delete();
            ContentRevision::where(['kind' => $kind, 'entity_id' => $root->id])->delete();
            $this->audit->record('content.delete', $kind, $root->id);
        });
    }

    /**
     * @param  array<int, int|string>  $questionIds
     * @return array<int, int>
     */
    public function questionsUsedInDraftExams(array $questionIds): array
    {
        $candidateIds = array_fill_keys(array_map('intval', $questionIds), true);
        $usedQuestionIds = [];

        foreach (ExamTemplate::whereNotNull('draft_payload')->cursor() as $exam) {
            foreach ($exam->draft_payload['sections'] ?? [] as $section) {
                foreach ($section['question_ids'] ?? [] as $questionId) {
                    $questionId = (int) $questionId;
                    if (isset($candidateIds[$questionId])) {
                        $usedQuestionIds[$questionId] = $questionId;
                    }
                }
            }

            if (count($usedQuestionIds) === count($candidateIds)) {
                break;
            }
        }

        return array_values($usedQuestionIds);
    }

    private function mirrorStatus(string $kind, Model $root, string $status): void
    {
        $item = $kind === 'question' ? ($root->learningItem ?? $root) : $root;
        if ($kind === 'question' && $item !== $root) {
            $root->forceFill(['status' => $status, 'published_at' => $status === 'published' ? now() : $root->published_at])->save();
        }
        $item->forceFill(['status' => $status, 'published_at' => $status === 'published' ? now() : $item->published_at])->save();
    }

    private function publishKnowledge(Model $item, array $payload): void
    {
        $item->forceFill(['canonical_text' => $payload['english'], 'metadata' => $payload, 'learning_item_type_id' => DB::table('learning_item_types')->where('code', $payload['type'] ?? 'vocabulary')->value('id'), 'proficiency_level_id' => $payload['proficiency_level_id'] ?? null])->save();
        $tagIds = [];
        foreach (array_filter(array_map('trim', explode(',', $payload['tags'] ?? ''))) as $tag) {
            $slug = Str::slug($tag);
            if (! $slug) {
                continue;
            }
            DB::table('tags')->updateOrInsert(['slug' => $slug], ['name' => $tag, 'created_at' => now(), 'updated_at' => now()]);
            $tagIds[] = DB::table('tags')->where('slug', $slug)->value('id');
        }
        DB::table('learning_item_tags')->where('learning_item_id', $item->id)->delete();
        foreach (array_unique($tagIds) as $tagId) {
            DB::table('learning_item_tags')->insert(['learning_item_id' => $item->id, 'tag_id' => $tagId]);
        }
        DB::table('learning_item_relations')->where('source_learning_item_id', $item->id)->delete();
        foreach ($payload['relations'] ?? [] as $relation) {
            if ((int) $relation['id'] === $item->id) {
                continue;
            } DB::table('learning_item_relations')->insert(['source_learning_item_id' => $item->id, 'target_learning_item_id' => $relation['id'], 'relation_type' => $relation['type'], 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('learning_item_meanings')->updateOrInsert(['learning_item_id' => $item->id, 'sense_number' => 1], ['translation' => $payload['meaning_vi'] ?? $payload['summary'] ?? $payload['english'], 'definition' => $payload['definition_en'] ?? null, 'part_of_speech_id' => $payload['part_of_speech_id'] ?? null, 'part_of_speech' => DB::table('parts_of_speech')->where('id', $payload['part_of_speech_id'] ?? null)->value('code'), 'updated_at' => now(), 'created_at' => now()]);
        $meaningId = DB::table('learning_item_meanings')->where('learning_item_id', $item->id)->where('sense_number', 1)->value('id');
        DB::table('learning_item_meaning_examples')->where('learning_item_meaning_id', $meaningId)->delete();
        foreach ($payload['examples'] ?? [] as $index => $example) {
            DB::table('learning_item_meaning_examples')->insert(['learning_item_meaning_id' => $meaningId, 'english' => $example['english'], 'vietnamese' => $example['vietnamese'] ?? null, 'sort_order' => $index, 'created_at' => now(), 'updated_at' => now()]);
        }
        $type = DB::table('learning_item_types')->where('id', $item->learning_item_type_id)->value('code');
        if ($type === 'vocabulary') {
            DB::table('dictionary_entries')->updateOrInsert(['learning_item_id' => $item->id], ['phonetic' => $payload['ipa'] ?? null, 'updated_at' => now(), 'created_at' => now()]);
        }
        if ($type === 'collocation') {
            DB::table('collocations')->updateOrInsert(['learning_item_id' => $item->id], ['grammatical_pattern' => $payload['grammatical_pattern'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            $collocationId = DB::table('collocations')->where('learning_item_id', $item->id)->value('id');
            DB::table('collocation_dictionary_entries')->where('collocation_id', $collocationId)->delete();
            foreach (array_unique($payload['dictionary_entry_ids'] ?? []) as $entryId) {
                DB::table('collocation_dictionary_entries')->insert(['collocation_id' => $collocationId, 'dictionary_entry_id' => $entryId]);
            }
        }
        if ($type === 'phrasal_verb') {
            DB::table('phrasal_verbs')->updateOrInsert(['learning_item_id' => $item->id], ['base_dictionary_entry_id' => $payload['base_dictionary_entry_id'], 'particle' => $payload['particle'], 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($type === 'idiom') {
            DB::table('idioms')->updateOrInsert(['learning_item_id' => $item->id], ['register' => $payload['register'] ?? null, 'usage_note' => $payload['usage_note'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($type === 'grammar') {
            DB::table('grammar_topics')->updateOrInsert(['learning_item_id' => $item->id], ['grammar_category_id' => $payload['grammar_category_id'] ?? DB::table('grammar_categories')->value('id'), 'summary' => $payload['summary'] ?? null, 'content' => $payload['content'] ?? null, 'category' => DB::table('grammar_categories')->where('id', $payload['grammar_category_id'] ?? DB::table('grammar_categories')->value('id'))->value('slug'), 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($type === 'grammar') {
            $topicId = DB::table('grammar_topics')->where('learning_item_id', $item->id)->value('id');
            DB::table('grammar_rules')->where('grammar_topic_id', $topicId)->delete();
            foreach ($payload['grammar_rules'] ?? [] as $index => $rule) {
                DB::table('grammar_rules')->insert(['grammar_topic_id' => $topicId, 'title' => $rule['title'], 'formula' => $rule['formula'] ?? null, 'explanation' => $this->registry->sanitize($rule['explanation'] ?? ''), 'sort_order' => $index, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
