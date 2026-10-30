<?php

namespace VistiqCore\Services\Assessment\QuestionBank;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Models\QuestionDifficulty;
use VistiqCore\Models\QuestionType;

/** Current admin content only. Historical references remain protected by the revision services. */
class QuestionBankCatalog
{
    private ?array $loaded = null;

    public function catalog(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }
        $questions = QuestionBankItem::with(['learningItem', 'examPart.examType', 'examPart.skill', 'questionType', 'difficulty', 'stimulus', 'draftRevision', 'publishedRevision', 'options', 'acceptedAnswers'])->get()->keyBy('id');
        $stimuli = LearningStimulus::with(['creator'])->get()->keyBy('id');
        $assets = MediaAsset::get()->keyBy('id');
        $groups = DB::table('question_groups')->get()->keyBy('id');
        $parts = ExamPart::with(['examType', 'skill'])->get()->keyBy('id');
        $types = QuestionType::get()->keyBy('code');
        $difficulties = QuestionDifficulty::get()->keyBy('id');
        $revisions = ContentRevision::whereIn('id', $stimuli->flatMap(fn ($s) => [$s->draft_revision_id, $s->published_revision_id])->filter())->get()->keyBy('id');
        $entries = collect();
        $audioByAsset = [];
        foreach ($stimuli as $s) {
            $revision = $revisions->get($s->draft_revision_id) ?? $revisions->get($s->published_revision_id);
            $p = $revision?->payload ?? ['title' => $s->title, 'body' => $s->body, 'transcript' => $s->transcript, 'asset_path' => $s->asset_path, 'metadata' => $s->metadata ?? []];
            $kind = $s->stimulus_type === 'audio' ? 'audio' : (($p['metadata']['content_kind'] ?? '') === 'paragraph' ? 'paragraph' : 'reading');
            $key = 'stimulus:'.$s->id;
            $lessonCode = trim((string) ($p['metadata']['lesson_code'] ?? ''));
            $entryCode = $lessonCode !== '' ? $lessonCode : ($kind === 'audio' ? 'A' : ($kind === 'paragraph' ? 'T' : 'P')).'-'.str_pad((string) $s->id, 5, '0', STR_PAD_LEFT);
            $entries[$key] = $this->entry($key, 'stimulus', $s->id, $entryCode, $p['title'] ?? $s->title, $kind, $p, $s, $s->status === 'archived' ? 'archived' : ($revision?->status ?? $s->status), $revision?->created_at ?? $s->updated_at);
            $assetId = (int) ($p['metadata']['asset_id'] ?? 0);
            if ($kind === 'audio' && $assetId) {
                $audioByAsset[$assetId] ??= $key;
            }
        }
        $records = [];
        foreach ($questions as $q) {
            $revision = $q->draftRevision ?? $q->publishedRevision;
            $p = $revision?->payload ?? [
                'prompt' => $q->prompt, 'explanation' => $q->explanation, 'exam_part_id' => $q->exam_part_id, 'type' => $q->questionType?->code,
                'question_difficulty_id' => $q->question_difficulty_id, 'metadata' => $q->metadata ?? [], 'stimulus_ids' => array_filter([$q->learning_stimulus_id]),
                'group_id' => $q->question_group_id, 'group_order' => $q->group_order, 'options' => $q->options->pluck('content', 'option_key')->all(),
                'correct_options' => $q->options->where('is_correct', true)->pluck('option_key')->all(), 'accepted_answers' => $q->acceptedAnswers->pluck('answer_text')->all(),
            ];
            $part = $parts->get($p['exam_part_id'] ?? $q->exam_part_id);
            // Relations used by the existing question preview must reflect the current revision.
            $q->setRelation('examPart', $part);
            $q->setRelation('questionType', $types->get($p['type'] ?? $q->questionType?->code));
            $q->setRelation('difficulty', $difficulties->get($p['question_difficulty_id'] ?? $q->question_difficulty_id));
            $group = $groups->get((int) ($p['group_id'] ?? 0));
            $groupMetadata = $group ? (json_decode($group->metadata ?? '{}', true) ?: []) : [];
            $groupStimuli = $group ? (json_decode($group->stimulus_ids ?? '[]', true) ?: []) : [];
            $lessonCode = trim((string) ($groupMetadata['lesson_code'] ?? ''));
            if ($group && $part?->skill?->code === 'listening' && $lessonCode === '') {
                $lessonCode = trim((string) ($groupMetadata['audio_group'] ?? ''));
            }
            $lessonKey = null;
            if ($group && $lessonCode !== '') {
                $lessonKey = 'group:'.$group->id;
                $audioId = (int) ($p['metadata']['audio_id'] ?? $groupMetadata['audio_id'] ?? 0);
                $entries[$lessonKey] ??= $this->entry($lessonKey, 'group', (int) $group->id, $lessonCode, $group->title, $audioId ? 'audio' : 'group', ['metadata' => ['lesson_code' => $lessonCode, 'audio_id' => $audioId, 'asset_id' => $audioId]], null, null, Carbon::parse($group->updated_at));
            }
            $ids = collect(array_merge($p['stimulus_ids'] ?? [], $groupStimuli))->map(fn ($id) => (int) $id)->filter()->unique();
            $q->setRelation('stimulus', $stimuli->get($ids->first()));
            $keys = $ids->map(fn ($id) => 'stimulus:'.$id)->filter(fn ($key) => $entries->has($key))->values()->all();
            $audioId = (int) ($p['metadata']['audio_id'] ?? $groupMetadata['audio_id'] ?? 0);
            if ($audioId) {
                $existingAudio = collect($keys)->first(fn ($key) => $entries[$key]['content_kind'] === 'audio' && (int) ($entries[$key]['payload']['metadata']['asset_id'] ?? 0) === $audioId);
                $key = $existingAudio ?? $audioByAsset[$audioId] ?? 'media:'.$audioId;
                if (! $entries->has($key)) {
                    $asset = $assets->get($audioId);
                    $entries[$key] = $this->entry($key, 'media', $audioId, 'A-M'.$audioId, $asset?->title ?? 'Audio không còn tồn tại', 'audio', ['metadata' => ['asset_id' => $audioId]], null, null, $asset?->updated_at ?? $q->updated_at);
                }
                $keys[] = $key;
            }
            if ($keys === [] && $group) {
                $key = 'group:'.$group->id;
                $entries[$key] ??= $this->entry($key, 'group', $group->id, 'G-'.str_pad((string) $group->id, 5, '0', STR_PAD_LEFT), $group->title, $group->kind === 'part6' ? 'paragraph' : 'group', [], null, null, Carbon::parse($group->updated_at));
                $keys[] = $key;
            }
            if ($lessonKey !== null) {
                $keys[] = $lessonKey;
            }
            if ($keys === []) {
                $key = 'question:'.$q->id;
                $entries[$key] = $this->entry($key, 'question', $q->id, 'Q-'.str_pad((string) $q->id, 5, '0', STR_PAD_LEFT), strip_tags($p['prompt'] ?? ''), 'question', [], null, null, $q->updated_at);
                $keys[] = $key;
            }
            $status = $q->contentStatus() === 'archived' ? 'archived' : ($revision?->status ?? $q->contentStatus() ?? 'draft');
            $kinds = collect($keys)->map(fn ($key) => $entries[$key]['content_kind']);
            $hasAudio = $kinds->contains('audio');
            $hasReading = collect($keys)->contains(fn ($key) => $entries[$key]['kind'] === 'stimulus' && in_array($entries[$key]['content_kind'], ['reading', 'paragraph']));
            $missing = [];
            if ($part?->skill?->code === 'listening' && (! $hasAudio || ($audioId && ! $assets->has($audioId)))) {
                $missing[] = 'audio';
            }
            if ($part?->skill?->code === 'reading' && ($part?->examType?->code === 'ielts' || in_array($part?->code, ['part-6', 'part-7'])) && ! $hasReading) {
                $missing[] = 'bài đọc';
            }
            if ((in_array($p['type'] ?? '', ['diagram_label_completion', 'plan_map_labelling']) || ($part?->examType?->code === 'toeic' && $part?->code === 'part-1')) && ! $assets->has($p['metadata']['image_id'] ?? 0)) {
                $missing[] = 'ảnh';
            }
            foreach ($keys as $key) {
                $e = $entries[$key];
                if ($e['content_kind'] === 'audio' && empty($e['payload']['asset_path']) && ! $assets->has($e['payload']['metadata']['asset_id'] ?? 0)) {
                    $missing[] = 'audio';
                }
                $e['questions']->put($q->id, $q);
                $e['updated_at'] = max($e['updated_at'], $revision?->created_at ?? $q->updated_at);
                $entries[$key] = $e;
            }
            $records[$q->id] = ['question' => $q, 'payload' => $p, 'status' => $status, 'keys' => array_values(array_unique($keys)), 'audio' => $hasAudio, 'reading' => $hasReading, 'missing' => array_values(array_unique($missing))];
        }
        $entries = $entries->map(function ($e) use ($records, $parts) {
            $e['questions'] = $e['questions']->sortBy(fn ($q) => sprintf('%06d-%012d', $records[$q->id]['payload']['group_order'] ?? 0, $q->id));
            $statuses = $e['questions']->map(fn ($q) => $records[$q->id]['status'])->unique();
            if (! $e['status']) {
                $e['status'] = $statuses->count() > 1 ? 'mixed' : ($statuses->first() ?? 'draft');
            }
            $difficulties = $e['questions']->map(fn ($q) => $q->difficulty?->code ?? '')->unique();
            $e['difficulty'] = $difficulties->count() > 1 ? 'mixed' : ($difficulties->first() ?? '');
            $e['question_status'] = $statuses->count() > 1 ? 'mixed' : ($statuses->first() ?? 'draft');
            $e['parts'] = $e['questions']->map(fn ($q) => $q->examPart)->filter()->unique('id')->values();
            if ($e['parts']->isEmpty() && isset($e['payload']['metadata']['exam_part_id'])) {
                $e['parts'] = collect([$parts->get($e['payload']['metadata']['exam_part_id'])])->filter();
            }
            if ($e['content_kind'] === 'reading' && $e['parts']->isNotEmpty() && $e['parts']->every(fn ($p) => $p->examType?->code === 'toeic' && $p->code === 'part-6')) {
                $e['content_kind'] = 'paragraph';
                $e['code'] = preg_replace('/^P-/', 'T-', $e['code']);
            }

            return $e;
        });

        return $this->loaded = compact('questions', 'entries', 'records', 'assets', 'stimuli');
    }

    private function entry(string $key, string $kind, int $id, string $code, string $title, string $contentKind, array $payload, ?LearningStimulus $stimulus, ?string $status, $updated): array
    {
        return ['key' => $key, 'kind' => $kind, 'id' => $id, 'code' => $code, 'title' => $title, 'content_kind' => $contentKind, 'payload' => $payload, 'stimulus' => $stimulus, 'status' => $status, 'updated_at' => $updated, 'questions' => collect()];
    }

    public function entryFor(string $kind, int $id): array
    {
        $entry = $this->catalog()['entries']->get($kind.':'.$id);
        abort_unless($entry, 404);

        return $entry;
    }

    public function matching(array $filters): Collection
    {
        $data = $this->catalog();

        return $data['questions']->filter(function ($q) use ($data, $filters) {
            $r = $data['records'][$q->id];
            $p = $r['payload'];
            $part = $q->examPart;
            foreach (['exam' => $part?->examType?->code, 'part' => $part?->code, 'type' => $p['type'] ?? $q->questionType?->code, 'difficulty' => $q->difficulty?->code, 'status' => $r['status']] as $name => $actual) {
                if (filled($filters[$name] ?? '') && $filters[$name] !== $actual) {
                    return false;
                }
            }
            foreach (['audio', 'reading'] as $name) {
                if (filled($filters[$name] ?? '') && ($filters[$name] === 'yes') !== $r[$name]) {
                    return false;
                }
            }
            if (($filters['missing'] ?? false) && $r['missing'] === []) {
                return false;
            }
            $search = mb_strtolower(trim($filters['search'] ?? ''));
            if ($search === '') {
                return true;
            }
            if (ctype_digit($search) && (int) $search === $q->id) {
                return true;
            }
            if (preg_match('/^(q|p|a|t|g)-0*(\d+)$/i', $search, $code)) {
                if ($code[1] === 'q' && (int) $code[2] === $q->id) {
                    return true;
                }
                foreach ($r['keys'] as $key) {
                    $entry = $data['entries'][$key];
                    if (strtolower(explode('-', $entry['code'])[0]) === $code[1] && $entry['id'] === (int) $code[2]) {
                        return true;
                    }
                }
            }
            $texts = ['Q-'.str_pad((string) $q->id, 5, '0', STR_PAD_LEFT), strip_tags($p['prompt'] ?? '')];
            foreach ($r['keys'] as $key) {
                $e = $data['entries'][$key];
                $texts[] = $e['code'].' '.$e['title'].' '.strip_tags($e['payload']['body'] ?? '');
            }

            return collect($texts)->contains(fn ($text) => str_contains(mb_strtolower($text), $search));
        });
    }
}
