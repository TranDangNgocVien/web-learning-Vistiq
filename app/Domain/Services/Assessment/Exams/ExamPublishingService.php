<?php

namespace VistiqCore\Services\Assessment\Exams;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\ExamRevision;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Assessment\QuestionBank\MediaService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;
use VistiqCore\Services\System\AuditService;

class ExamPublishingService
{
    public const POLICIES = [
        'duration_minutes' => 30, 'pause_allowed' => false, 'auto_submit' => true, 'navigation' => 'free', 'lock_previous' => false, 'review_later' => true,
        'answer_review' => 'after_submit', 'explanation_review' => 'after_submit', 'require_fullscreen' => false,
        'disable_selection' => false, 'disable_copy' => false, 'disable_paste' => false, 'disable_context_menu' => false,
        'track_focus' => true, 'blur_on_focus_loss' => false, 'watermark' => false, 'randomize_options' => false,
        'warning_threshold' => 2, 'auto_submit_threshold' => 0, 'event_logging' => true, 'allow_mobile' => true,
        'leaderboard' => true, 'anonymous' => true, 'strict_audio' => false,
    ];

    public const FULL_SIMULATION_POLICIES = [
        'pause_allowed' => false,
        'auto_submit' => true,
        'answer_review' => 'after_submit',
        'explanation_review' => 'after_submit',
    ];

    public function __construct(private ContentRevisionService $revisions, private QuestionRegistry $registry, private MediaService $media, private AuditService $audit) {}

    public static function normalizePolicies(string $mode, array $policies = []): array
    {
        $normalized = array_replace(self::POLICIES, $policies);

        return $mode === 'full' ? array_replace($normalized, self::FULL_SIMULATION_POLICIES) : $normalized;
    }

    public function save(ExamTemplate $exam, array $data, ?int $expected): ExamTemplate
    {
        Gate::authorize('edit', $exam);
        $data['policies'] = self::normalizePolicies((string) ($data['mode'] ?? $exam->mode ?? ''), $data['policies'] ?? []);
        $totalMinutes = (int) ($data['policies']['duration_minutes'] ?? self::POLICIES['duration_minutes']);
        $sectionMinutes = collect($data['sections'] ?? [])->sum(
            fn ($section) => is_array($section) ? max(0, (int) ($section['duration_minutes'] ?? 0)) : 0,
        );
        if ($sectionMinutes > $totalMinutes) {
            throw ValidationException::withMessages(['sections' => 'Tổng thời gian các phần không được vượt thời gian làm bài của đề.']);
        }
        app(ExamGenerationService::class)->validateSelection($data);

        return DB::transaction(function () use ($exam, $data, $expected): ExamTemplate {
            $exam = ExamTemplate::lockForUpdate()->findOrFail($exam->id);
            abort_if($exam->revision_counter > 0 && $exam->revision_counter !== $expected, 409, 'Đề đã thay đổi. Hãy tải lại.');
            $templateCard = $data['visual_template'] ?? null;
            unset($data['visual_template']);
            $exam->fill($data);
            $exam->draft_payload = ['sections' => $data['sections'] ?? [], 'policies' => array_replace(self::POLICIES, $data['policies'] ?? []), 'generation' => $data['generation'] ?? null, 'template_card' => $templateCard];
            $exam->status = 'draft';
            $exam->draft_status = 'draft';
            $exam->revision_counter++;
            $exam->save();
            $this->audit->record('exam.save', 'exam', $exam->id, [], ['revision' => $exam->revision_counter]);

            return $exam;
        });
    }

    public function assemble(ExamTemplate $exam, bool $preview = false): array
    {
        $exam->load('examType');
        $draft = $exam->draft_payload ?? [];
        $sections = [];
        $items = [];
        $ids = [];
        $policies = self::normalizePolicies((string) $exam->mode, $draft['policies'] ?? []);
        foreach ($draft['sections'] ?? [] as $index => $section) {
            $part = ExamPart::with('skill')->findOrFail($section['exam_part_id']);
            if ($part->exam_type_id !== $exam->exam_type_id) {
                throw ValidationException::withMessages(['sections' => 'Section thuộc kỳ thi khác.']);
            }
            $sectionItems = [];
            foreach ($section['question_ids'] ?? [] as $questionId) {
                if (in_array($questionId, $ids, true)) {
                    throw ValidationException::withMessages(['sections' => 'Không dùng một câu hỏi nhiều lần trong đề.']);
                }
                $ids[] = $questionId;
                $question = QuestionBankItem::with(['learningItem', 'questionType'])->findOrFail($questionId);
                $questionRevision = $preview ? null : $this->revisions->approvedOrPublishedRevision('question', $question);
                if (! $preview && ! $questionRevision && ! $question->published_revision_id && $question->contentStatus() === 'published') {
                    $questionRevision = $this->revisions->published('question', $question);
                }
                if (! $preview && ! $questionRevision) {
                    throw ValidationException::withMessages(['exam' => 'Câu hỏi #'.$question->id.' chưa được duyệt. Hãy duyệt câu hỏi trong ngân hàng câu hỏi trước khi gửi duyệt đề.']);
                }
                $payload = $preview ? $this->revisions->payload('question', $question, empty($draft['generation'])) : $questionRevision->payload;
                if ($question->contentStatus() === 'archived' || (int) $payload['exam_part_id'] !== $part->id) {
                    throw ValidationException::withMessages(['sections' => 'Câu hỏi đã archive hoặc không thuộc Part đã chọn.']);
                }
                if (! $preview) {
                    $this->registry->validate($payload);
                }
                $payload['key'] = 'q'.$question->id;
                $payload['section'] = $index;
                $payload['revision_id'] = $preview ? $question->published_revision_id : $questionRevision->id;
                $payload['group'] = ! empty($payload['group_id']) ? (array) DB::table('question_groups')->find($payload['group_id']) : null;
                $stimulusIds = $payload['stimulus_ids'] ?? [];
                if ($payload['group']) {
                    $stimulusIds = array_merge($stimulusIds, json_decode($payload['group']['stimulus_ids'], true));
                }
                $payload['stimuli'] = [];
                foreach (array_unique($stimulusIds) as $stimulusId) {
                    $stimulus = LearningStimulus::findOrFail($stimulusId);
                    $stimulusRevision = $preview ? null : $this->revisions->approvedOrPublishedRevision('stimulus', $stimulus);
                    if (! $preview && ! $stimulusRevision && ! $stimulus->published_revision_id && $stimulus->status === 'published') {
                        $stimulusRevision = $this->revisions->published('stimulus', $stimulus);
                    }
                    $stimulusPayload = $preview
                        ? $this->revisions->payload('stimulus', $stimulus, empty($draft['generation']))
                        : ($stimulusRevision?->payload ?? $this->revisions->payload('stimulus', $stimulus));
                    if (! $preview && $stimulus->status === 'archived') {
                        throw ValidationException::withMessages(['media' => 'Tài nguyên đã archive.']);
                    }
                    $stimulusPayload['revision_id'] = $preview
                        ? $stimulus->published_revision_id
                        : ($stimulusRevision?->id ?? $stimulus->draft_revision_id ?? $stimulus->published_revision_id);
                    $payload['stimuli'][] = $stimulusPayload;
                }
                $payload['weight'] = ($payload['part'] ?? '') === 'writing-task-2' ? 2 : 1;
                $items[] = $payload;
                $sectionItems[] = $payload['key'];
            }
            $skill = $part->skill?->code;
            $group = $exam->mode === 'full' ? ($skill ?? 'section-'.$index) : 'section-'.$index;
            $groupDuration = $exam->mode === 'full' ? match ($skill) {
                'listening' => $exam->examType->code === 'toeic' ? 45 : 30,'reading' => $exam->examType->code === 'toeic' ? 75 : 60,'writing' => 60,'speaking' => 14,default => (int) ($section['duration_minutes'] ?? $policies['duration_minutes'])
            } : (int) ($section['duration_minutes'] ?? 0);
            $sections[] = ['index' => $index, 'exam_part_id' => $part->id, 'part' => $part->code, 'skill' => $skill, 'title' => $part->name, 'item_keys' => $sectionItems, 'timing_group' => $group, 'duration_minutes' => $groupDuration];
        }
        $scale = $exam->score_scale_id ? DB::table('score_scales')->find($exam->score_scale_id) : null;

        return ['schema_version' => 1, 'exam_id' => $exam->id, 'title' => $exam->title, 'exam_type' => $exam->examType->code, 'variant' => $exam->variant, 'mode' => $exam->mode, 'opens_at' => $exam->opens_at?->toIso8601String(), 'closes_at' => $exam->closes_at?->toIso8601String(), 'sections' => $sections, 'items' => $items, 'policies' => $policies, 'scoring' => $scale ? ['id' => $scale->id, 'name' => $scale->name, 'version' => $scale->version, 'configuration' => json_decode($scale->configuration ?? '{}', true)] : null, 'estimated' => true];
    }

    public function problems(array $snapshot): array
    {
        $errors = [];
        $items = $snapshot['items'];
        $sections = $snapshot['sections'];
        $policies = $snapshot['policies'];
        $isFull = $snapshot['mode'] === 'full';
        if ($items === []) {
            $errors[] = 'Đề chưa có câu hỏi.';
        }
        if (($policies['duration_minutes'] ?? 0) < 1) {
            $errors[] = 'Thiếu thời gian làm bài.';
        }
        foreach ($sections as $section) {
            if ($section['item_keys'] === []) {
                $errors[] = $section['title'].': chưa có câu hỏi.';
            }
        }
        if ($snapshot['exam_type'] === 'ielts' && empty($snapshot['variant']) && collect($items)->contains(fn ($item) => in_array($item['skill'] ?? '', ['reading', 'writing'], true))) {
            $errors[] = 'Đề IELTS có Reading/Writing cần chọn phiên bản Academic hoặc General trong phần Chỉnh sửa đề thi.';
        }
        foreach ($items as $item) {
            try {
                $this->registry->validate($item);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $messages) {
                    $errors[] = ($item['key'] ?? 'Câu hỏi').': '.implode(' ', (array) $messages);
                }
            }
            $skill = $item['skill'] ?? '';
            $metadata = $item['metadata'] ?? [];
            $stimuli = $item['stimuli'] ?? [];
            $hasAudio = ! empty($metadata['audio_id']) || collect($stimuli)->contains('stimulus_type', 'audio');
            $hasReading = collect($stimuli)->contains('stimulus_type', 'reading');
            if ($skill === 'listening' && ! $hasAudio) {
                $errors[] = $item['key'].': thiếu audio.';
            }
            if ($snapshot['exam_type'] === 'toeic' && $item['part'] === 'part-1' && empty($metadata['image_id'])) {
                $errors[] = $item['key'].': thiếu ảnh Part 1.';
            }
            if ($skill === 'reading' && ($snapshot['exam_type'] === 'ielts' || in_array($item['part'], ['part-6', 'part-7'], true)) && ! $hasReading) {
                $errors[] = $item['key'].': thiếu passage.';
            }
            if ($snapshot['exam_type'] === 'ielts' && in_array($skill, ['reading', 'writing'], true) && ! empty($snapshot['variant']) && ($metadata['variant'] ?? null) !== $snapshot['variant']) {
                $questionVariant = match ($metadata['variant'] ?? null) {
                    'academic' => 'Academic', 'general' => 'General Training', default => 'chưa chọn phiên bản',
                };
                $examVariant = $snapshot['variant'] === 'academic' ? 'Academic' : 'General Training';
                $errors[] = $item['key'].': câu hỏi '.$questionVariant.', đề '.$examVariant.'. Chọn câu hỏi cùng phiên bản hoặc sửa phiên bản đề.';
            }
            if ($snapshot['exam_type'] === 'thpt' && (($item['type'] ?? null) !== 'multiple_choice' || count($item['options'] ?? []) !== 4)) {
                $errors[] = $item['key'].': câu THPT cần có đúng 4 lựa chọn trắc nghiệm.';
            }
            foreach (array_filter([$metadata['image_id'] ?? null, $metadata['audio_id'] ?? null]) as $assetId) {
                if (! $this->media->available((int) $assetId)) {
                    $errors[] = $item['key'].': media không tồn tại.';
                }
            }
            foreach ($stimuli as $stimulus) {
                if (! empty($stimulus['metadata']['asset_id']) && ! $this->media->available((int) $stimulus['metadata']['asset_id'])) {
                    $errors[] = $item['key'].': file của tài nguyên không tồn tại.';
                }
                if (! empty($stimulus['asset_path']) && ! filter_var($stimulus['asset_path'], FILTER_VALIDATE_URL) && ! Storage::disk('public')->exists($stimulus['asset_path'])) {
                    $errors[] = $item['key'].': file cũ không tồn tại.';
                }
            }
        }
        if ($isFull) {
            if ($snapshot['exam_type'] === 'ielts' && ! in_array($snapshot['variant'], ['academic', 'general'], true)) {
                $errors[] = 'Full IELTS cần chọn variant Academic hoặc General.';
            }
            if (count(array_column($sections, 'exam_part_id')) !== count(array_unique(array_column($sections, 'exam_part_id')))) {
                $errors[] = 'Full Simulation không được trùng Part/section.';
            }
            if ($snapshot['exam_type'] === 'ielts' && (int) $policies['duration_minutes'] < 164) {
                $errors[] = 'Full IELTS cần đủ thời gian Listening 30, Reading 60, Writing 60 và Speaking 14 phút.';
            }
            if ($policies['pause_allowed'] || ! $policies['auto_submit'] || $policies['answer_review'] === 'after_each' || $policies['explanation_review'] === 'after_each') {
                $errors[] = 'Full Simulation: không pause, phải auto-submit và không hiện đáp án lúc thi.';
            }
            if ($snapshot['exam_type'] === 'thpt') {
                $englishParts = ['tieng-anh-part-1' => 6, 'tieng-anh-part-2' => 6, 'tieng-anh-part-3' => 5, 'tieng-anh-part-4' => 5, 'tieng-anh-part-5' => 8, 'tieng-anh-part-6' => 10];
                $isEnglishBlueprint = count($sections) === count($englishParts)
                    && collect($sections)->every(fn ($section) => array_key_exists($section['part'], $englishParts));
                if ($isEnglishBlueprint) {
                    foreach ($englishParts as $part => $count) {
                        $actual = collect($items)->where('part', $part)->sum(fn ($item) => $this->registry->units($item));
                        if ($actual !== $count) {
                            $errors[] = $part.': cần '.$count.' câu, hiện có '.$actual.'.';
                        }
                    }
                    if ((int) $policies['duration_minutes'] !== 50) {
                        $errors[] = 'Tiếng Anh THPT cần 50 phút.';
                    }
                } else {
                    if (count($sections) !== 1) {
                        $errors[] = 'Đề THPT Tiếng Anh cần một môn hoặc đúng sáu phần theo cấu trúc đề.';
                    }
                    $section = $sections[0] ?? null;
                    $subject = $section && $section['part'] === 'tieng-anh' ? collect(ExamGenerationService::THPT_SUBJECTS)->first() : null;
                    if (! $subject) {
                        $errors[] = 'Đề THPT hiện chỉ hỗ trợ môn Tiếng Anh.';
                    } else {
                        $actual = collect($items)->where('part', $section['part'])->sum(fn ($item) => $this->registry->units($item));
                        if ($actual !== $subject['count']) {
                            $errors[] = $subject['name'].': cần '.$subject['count'].' câu, hiện có '.$actual.'.';
                        }
                        if ((int) $section['duration_minutes'] !== $subject['duration_minutes'] || (int) $policies['duration_minutes'] !== $subject['duration_minutes']) {
                            $errors[] = $subject['name'].': thời gian cần '.$subject['duration_minutes'].' phút.';
                        }
                    }
                }
            } elseif ($snapshot['exam_type'] === 'toeic') {
                foreach ([1 => 6, 2 => 25, 3 => 39, 4 => 30, 5 => 30, 6 => 16, 7 => 54] as $part => $count) {
                    $actual = collect($items)->where('part', 'part-'.$part)->sum(fn ($item) => $this->registry->units($item));
                    if ($actual !== $count) {
                        $errors[] = 'Part '.$part.': '.$actual.' / '.$count.' câu.';
                    }
                }
                foreach (['part-3' => 3, 'part-4' => 3, 'part-6' => 4] as $part => $count) {
                    $groups = collect($items)->where('part', $part)->groupBy('group_id');
                    foreach ($groups as $groupId => $questions) {
                        if (! $groupId || $questions->count() !== $count) {
                            $errors[] = $part.': nhóm phải có '.$count.' câu.';
                        }
                    }
                }
                $part7 = collect($items)->where('part', 'part-7');
                $single = $part7->filter(fn ($item) => count($item['stimuli']) === 1)->count();
                $multiple = $part7->filter(fn ($item) => in_array(count($item['stimuli']), [2, 3], true))->count();
                if ($single !== 29 || $multiple !== 25) {
                    $errors[] = 'Part 7 cần 29 câu single passage và 25 câu multiple passages.';
                }
                if ((int) $policies['duration_minutes'] !== 120) {
                    $errors[] = 'Full TOEIC cần 120 phút.';
                }
            } else {
                foreach (['listening' => [40, 4], 'reading' => [40, 3]] as $skill => [$count,$sectionCount]) {
                    $actual = collect($items)->where('skill', $skill)->sum(fn ($item) => $this->registry->units($item));
                    if ($actual !== $count || collect($sections)->where('skill', $skill)->count() !== $sectionCount) {
                        $errors[] = $skill.' cần '.$count.' câu / '.$sectionCount.' sections.';
                    }
                }
                if (collect($items)->where('skill', 'writing')->count() !== 2 || collect($items)->where('part', 'writing-task-1')->count() !== 1 || collect($items)->where('part', 'writing-task-2')->count() !== 1) {
                    $errors[] = 'Writing cần Task 1 và Task 2.';
                }
                if (collect($sections)->where('skill', 'speaking')->count() !== 3) {
                    $errors[] = 'Speaking cần 3 parts.';
                }
            }
            if (! $snapshot['scoring']) {
                $errors[] = 'Full Simulation cần bảng quy đổi điểm.';
            }
        }
        if ($snapshot['scoring']) {
            $configuration = $snapshot['scoring']['configuration'];
            $tables = $configuration['tables'] ?? [];
            if ($snapshot['exam_type'] === 'thpt' && $isFull) {
                $max = collect($items)->sum(fn ($item) => $this->registry->units($item));
                $table = $tables['thpt'] ?? [];
                for ($raw = 0; $raw <= $max; $raw++) {
                    if (! isset($table[$raw]) || ! is_numeric($table[$raw])) {
                        $errors[] = 'Bảng điểm THPT thiếu điểm raw '.$raw.'.';
                        break;
                    }
                }
                if (isset($table[$max]) && abs((float) $table[$max] - 10) > 0.01) {
                    $errors[] = 'Bảng điểm THPT cần quy đổi đủ số câu thành 10 điểm.';
                }
            }
            foreach (['listening', 'reading'] as $skill) {
                $max = collect($items)->where('skill', $skill)->sum(fn ($item) => $this->registry->units($item));
                if ($max === 0) {
                    continue;
                }
                $table = $tables[$skill] ?? [];
                for ($raw = 0; $raw <= $max; $raw++) {
                    if (! isset($table[$raw]) || ! is_numeric($table[$raw])) {
                        $errors[] = 'Bảng '.$skill.' thiếu điểm raw '.$raw.'.';
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($errors));
    }

    public function publish(ExamTemplate $exam): ExamRevision
    {
        Gate::authorize('content.publish');
        abort_unless($exam->draft_status === 'approved', 409, 'Đề cần được duyệt trước khi xuất hiện trong kho học viên.');

        return $this->approvedRevision($exam);
    }

    /**
     * Approval makes the reviewed exam available to learners immediately.
     * Keep an immutable revision for attempt snapshots, but do not introduce a
     * separate learner-visible publish state.
     */
    public function approve(ExamTemplate $exam, ?array $payload = null): ExamRevision
    {
        Gate::authorize('content.review');

        return DB::transaction(function () use ($exam, $payload): ExamRevision {
            $exam = ExamTemplate::lockForUpdate()->findOrFail($exam->id);
            abort_unless($exam->draft_status === 'review', 409, 'Đề không còn ở trạng thái chờ duyệt.');

            $payload ??= $this->assemble($exam);
            $errors = $this->problems($payload);
            if ($errors !== []) {
                throw ValidationException::withMessages(['approve' => $errors]);
            }

            $revision = ExamRevision::create([
                'exam_template_id' => $exam->id,
                'number' => $exam->revision_counter,
                'payload' => $payload,
                'published_by' => auth()->id(),
            ]);
            $exam->update([
                'status' => 'approved',
                'draft_status' => 'approved',
                'published_revision_id' => $revision->id,
                'published_at' => now(),
            ]);

            return $revision;
        }, 3);
    }

    /** Create a stable snapshot for exams approved before approval created one. */
    public function approvedRevision(ExamTemplate $exam): ExamRevision
    {
        return DB::transaction(function () use ($exam): ExamRevision {
            $exam = ExamTemplate::with('publishedRevision')->lockForUpdate()->findOrFail($exam->id);
            abort_unless(
                $exam->draft_status === 'approved'
                    || ($exam->status === 'published' && $exam->publishedRevision),
                404,
            );

            if ($exam->publishedRevision) {
                return $exam->publishedRevision;
            }

            $payload = $this->assemble($exam);
            $errors = $this->problems($payload);
            if ($errors !== []) {
                throw ValidationException::withMessages(['exam' => $errors]);
            }
            $revision = ExamRevision::create([
                'exam_template_id' => $exam->id,
                'number' => max(1, (int) $exam->revision_counter),
                'payload' => $payload,
                'published_by' => null,
            ]);
            $exam->update(['status' => 'approved', 'published_revision_id' => $revision->id]);

            return $revision;
        }, 3);
    }
}
