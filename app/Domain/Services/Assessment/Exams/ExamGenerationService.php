<?php

namespace VistiqCore\Services\Assessment\Exams;

use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Validation\ValidationException;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\ExamType;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;

class ExamGenerationService
{
    public const TEMPLATES = ['custom', 'toeic', 'ielts-academic', 'ielts-general', 'thpt', 'thpt-english', 'thpt-english-2025', 'thpt-english-2026'];

    public const THPT_ENGLISH_PARTS = [
        ['code' => 'tieng-anh-part-1', 'name' => 'Short cloze · Văn bản 1 · 6 câu', 'count' => 6],
        ['code' => 'tieng-anh-part-2', 'name' => 'Short cloze · Văn bản 2 · 6 câu', 'count' => 6],
        ['code' => 'tieng-anh-part-3', 'name' => 'Sắp xếp câu / lượt lời · 5 câu', 'count' => 5],
        ['code' => 'tieng-anh-part-4', 'name' => 'Extended completion · 5 câu', 'count' => 5],
        ['code' => 'tieng-anh-part-5', 'name' => 'Đọc hiểu · 8 câu', 'count' => 8],
        ['code' => 'tieng-anh-part-6', 'name' => 'Đọc hiểu · 10 câu', 'count' => 10],
    ];

    /** @var array<string, array{name: string, part_codes: list<string>}> */
    public const THPT_ENGLISH_BLUEPRINTS = [
        'thpt-english-2025' => [
            'name' => 'Tiếng Anh THPT · Mẫu A (tham khảo 2025)',
            'part_codes' => ['tieng-anh-part-1', 'tieng-anh-part-2', 'tieng-anh-part-3', 'tieng-anh-part-4', 'tieng-anh-part-5', 'tieng-anh-part-6'],
        ],
        'thpt-english-2026' => [
            'name' => 'Tiếng Anh THPT · Mẫu B (đề 2026, mã 1101)',
            'part_codes' => ['tieng-anh-part-2', 'tieng-anh-part-3', 'tieng-anh-part-4', 'tieng-anh-part-6', 'tieng-anh-part-1', 'tieng-anh-part-5'],
        ],
    ];

    public const THPT_SUBJECTS = [
        ['code' => 'tieng-anh', 'name' => 'Tiếng Anh', 'count' => 40, 'duration_minutes' => 50],
    ];

    public function __construct(private ContentRevisionService $revisions, private QuestionRegistry $registry) {}

    public function catalog(): array
    {
        $catalog = [];
        foreach (['toeic', 'ielts-academic', 'ielts-general'] as $key) {
            $type = ExamType::where('code', $key === 'toeic' ? 'toeic' : 'ielts')->first();
            if (! $type) {
                continue;
            }
            $counts = $key === 'toeic'
                ? ['part-1' => 6, 'part-2' => 25, 'part-3' => 39, 'part-4' => 30, 'part-5' => 30, 'part-6' => 16, 'part-7' => 54]
                : ['listening-part-1' => 10, 'listening-part-2' => 10, 'listening-part-3' => 10, 'listening-part-4' => 10, 'reading-part-1' => 13, 'reading-part-2' => 13, 'reading-part-3' => 14, 'writing-task-1' => 1, 'writing-task-2' => 1, 'speaking-part-1' => 1, 'speaking-part-2' => 1, 'speaking-part-3' => 1];
            $parts = ExamPart::where('exam_type_id', $type->id)->get()->keyBy('code');
            $catalog[$key] = ['name' => $key === 'toeic' ? 'TOEIC Listening & Reading' : 'IELTS '.($key === 'ielts-academic' ? 'Academic' : 'General Training'), 'exam_type_id' => $type->id, 'variant' => $key === 'toeic' ? null : substr($key, 6), 'duration_minutes' => $key === 'toeic' ? 120 : 164, 'sections' => []];
            foreach ($counts as $code => $count) {
                $catalog[$key]['sections'][] = ['exam_part_id' => $parts[$code]->id ?? null, 'count' => $count];
            }
        }

        $thpt = ExamType::where('code', 'thpt')->first();
        if ($thpt) {
            $parts = ExamPart::where('exam_type_id', $thpt->id)->get()->keyBy('code');
            $subjects = [];
            foreach (self::THPT_SUBJECTS as $subject) {
                if ($part = $parts->get($subject['code'])) {
                    $subjects[$part->code] = [...$subject, 'exam_part_id' => $part->id];
                }
            }
            $scaleNames = [34 => 'THPT luyện tập 34 câu · thang 10', 40 => 'THPT luyện tập 40 câu · thang 10'];
            $scaleIds = DB::table('score_scales')->whereIn('name', array_values($scaleNames))->pluck('id', 'name');
            $catalog['thpt'] = [
                'name' => 'Tốt nghiệp THPT · trắc nghiệm 4 lựa chọn',
                'exam_type_id' => $thpt->id,
                'variant' => null,
                'duration_minutes' => 90,
                'sections' => [],
                'subjects' => $subjects,
                'score_scales' => [34 => $scaleIds->get($scaleNames[34]), 40 => $scaleIds->get($scaleNames[40])],
            ];

            $englishSections = collect(self::THPT_ENGLISH_PARTS)->keyBy('code');
            foreach (self::THPT_ENGLISH_BLUEPRINTS as $key => $blueprint) {
                $sections = collect($blueprint['part_codes'])->map(function (string $code) use ($englishSections, $parts): array {
                    $part = $englishSections->get($code);

                    return ['exam_part_id' => $parts[$code]->id ?? null, 'code' => $code, 'count' => $part['count'] ?? 0];
                })->all();
                $catalog[$key] = [
                    'name' => $blueprint['name'],
                    'exam_type_id' => $thpt->id,
                    'variant' => null,
                    'duration_minutes' => 50,
                    'sections' => $sections,
                    'score_scales' => [40 => $scaleIds->get($scaleNames[40])],
                ];
            }
            // Keep drafts created with the original template key readable and valid.
            $catalog['thpt-english'] = $catalog['thpt-english-2025'];
        }

        return $catalog;
    }

    public function structure(array $data): array
    {
        $generation = $data['generation'];
        $preset = $this->catalog()[$generation['template']] ?? null;
        if ($generation['template'] !== 'custom') {
            if (! $preset || (int) $data['exam_type_id'] !== $preset['exam_type_id'] || ($data['variant'] ?? null) !== $preset['variant']) {
                throw ValidationException::withMessages(['generation' => 'Template không khớp kỳ thi hoặc biến thể đã chọn.']);
            }
            $expectedSections = $generation['template'] === 'thpt' ? 1 : count($preset['sections']);
            if (count($generation['sections']) !== $expectedSections) {
                throw ValidationException::withMessages(['generation' => 'Số phần không đúng template.']);
            }
        }
        foreach ($generation['sections'] as $index => &$section) {
            $part = ExamPart::find($section['exam_part_id']);
            if (! $part || $part->exam_type_id !== (int) $data['exam_type_id']) {
                throw ValidationException::withMessages(["generation.sections.$index" => 'Phần không thuộc kỳ thi đã chọn.']);
            }
            if ($part->examType?->code === 'thpt' && $part->code !== 'tieng-anh' && ! str_starts_with($part->code, 'tieng-anh-part-')) {
                throw ValidationException::withMessages(["generation.sections.$index" => 'Đề THPT hiện chỉ hỗ trợ môn Tiếng Anh.']);
            }
            $section['difficulty'] = array_map('intval', $section['difficulty']);
            $section['count'] = array_sum($section['difficulty']);
            if ($section['count'] < 1 || $section['count'] > 200) {
                throw ValidationException::withMessages(["generation.sections.$index" => 'Mỗi phần cần từ 1 đến 200 câu.']);
            }
            if ($generation['template'] === 'thpt') {
                $subject = $preset['subjects'][$part->code] ?? null;
                if (! $subject || $section['count'] !== $subject['count'] || (int) ($section['duration_minutes'] ?? 0) !== $subject['duration_minutes']) {
                    throw ValidationException::withMessages(["generation.sections.$index" => 'Môn, số câu hoặc thời gian không đúng cấu trúc đề THPT.']);
                }
            }
            if (str_starts_with($generation['template'], 'thpt-english')) {
                unset($section['duration_minutes']);
            }
            if ($preset && $generation['template'] !== 'thpt' && ($part->id !== $preset['sections'][$index]['exam_part_id'] || $section['count'] !== $preset['sections'][$index]['count'])) {
                throw ValidationException::withMessages(["generation.sections.$index" => 'Part hoặc tổng số câu không đúng template.']);
            }
        }
        unset($section);

        return $generation;
    }

    /** Randomize complete groups, counting answer units rather than database rows. */
    public function generate(array $data): array
    {
        $generation = $this->structure($data);
        $pool = $this->pool($data);
        $used = [];
        $sections = [];
        $summary = [];
        foreach ($generation['sections'] as $index => $rule) {
            $part = ExamPart::findOrFail($rule['exam_part_id']);
            $candidates = array_filter($pool, fn ($item) => $item['part_id'] === $part->id && ! in_array($item['id'], $used, true));
            $bundles = $this->bundles($candidates, $generation['template'], $part->code);
            $target = [$rule['difficulty']['easy'], $rule['difficulty']['medium'], $rule['difficulty']['hard'], $generation['template'] === 'toeic' && $part->code === 'part-7' ? 29 : 0];
            $selected = $this->select($bundles, $target);
            if ($selected === null) {
                $available = ['easy' => 0, 'medium' => 0, 'hard' => 0];
                foreach ($bundles as $bundle) {
                    foreach (array_keys($available) as $i => $difficulty) {
                        $available[$difficulty] += $bundle['counts'][$i];
                    }
                }
                throw ValidationException::withMessages(["generation.sections.$index" => $part->name.': không đủ câu hoặc nhóm nguyên vẹn để đạt cơ cấu đã chọn. Cần dễ/vừa/khó '.implode('/', $rule['difficulty']).'; khả dụng '.implode('/', $available).'. Kiểm tra nhóm câu, biến thể và trạng thái duyệt.']);
            }
            $ids = [];
            foreach ($selected as $bundle) {
                array_push($ids, ...$bundle['ids']);
            }
            array_push($used, ...$ids);
            $sections[] = ['exam_part_id' => $part->id, 'question_ids' => $ids, ...isset($rule['duration_minutes']) ? ['duration_minutes' => $rule['duration_minutes']] : []];
            $summary[] = ['title' => $part->name, 'count' => $rule['count'], 'difficulty' => $rule['difficulty']];
        }

        return ['sections' => $sections, 'generation' => $generation, 'summary' => $summary];
    }

    public function validateSelection(array $data): void
    {
        if (ExamType::whereKey($data['exam_type_id'] ?? null)->where('code', 'thpt')->exists()) {
            foreach ($data['sections'] ?? [] as $index => $section) {
                $part = ExamPart::find($section['exam_part_id'] ?? null);
                if (! $part || ($part->code !== 'tieng-anh' && ! str_starts_with($part->code, 'tieng-anh-part-'))) {
                    throw ValidationException::withMessages(["sections.$index" => 'Đề THPT hiện chỉ hỗ trợ môn Tiếng Anh.']);
                }
            }
        }
        if (empty($data['generation'])) {
            if (($data['mode'] ?? null) === 'full' && ExamType::whereKey($data['exam_type_id'] ?? null)->where('code', 'thpt')->exists()) {
                throw ValidationException::withMessages(['generation' => 'Đề THPT cần tạo bằng template môn thi để giữ đúng số câu và thời gian.']);
            }

            return;
        }
        $generation = $this->structure($data);
        if ($generation['template'] !== 'custom' && ($data['mode'] ?? '') !== 'full') {
            throw ValidationException::withMessages(['mode' => 'Template đề chuẩn cần chế độ Full Simulation.']);
        }
        $pool = $this->pool($data);
        $used = [];
        if (count($data['sections']) !== count($generation['sections'])) {
            throw ValidationException::withMessages(['sections' => 'Cấu trúc câu hỏi chưa khớp template. Hãy tạo câu hỏi ngẫu nhiên lại.']);
        }
        foreach ($data['sections'] as $index => $section) {
            $rule = $generation['sections'][$index];
            $ids = array_map('intval', $section['question_ids']);
            $part = ExamPart::findOrFail($rule['exam_part_id']);
            $bundles = $this->bundles(array_filter($pool, fn ($item) => $item['part_id'] === $part->id), $generation['template'], $part->code);
            $counts = [0, 0, 0, 0];
            $matched = [];
            foreach ($bundles as $bundle) {
                if (array_intersect($bundle['ids'], $ids)) {
                    if (array_diff($bundle['ids'], $ids)) {
                        throw ValidationException::withMessages(['sections' => $part->name.': cần giữ nguyên nhóm câu hỏi.']);
                    }
                    array_push($matched, ...$bundle['ids']);
                    foreach ($counts as $i => $value) {
                        $counts[$i] += $bundle['counts'][$i];
                    }
                }
            }
            $target = [$rule['difficulty']['easy'], $rule['difficulty']['medium'], $rule['difficulty']['hard'], $generation['template'] === 'toeic' && $part->code === 'part-7' ? 29 : 0];
            if ((int) $section['exam_part_id'] !== $part->id || count($ids) !== count(array_unique($ids)) || array_intersect($ids, $used) || array_diff($ids, $matched) || $counts !== $target) {
                throw ValidationException::withMessages(['sections' => $part->name.': câu hỏi không khớp số lượng, độ khó hoặc không còn khả dụng. Hãy tạo lại câu hỏi.']);
            }
            array_push($used, ...$ids);
        }
    }

    private function pool(array $data): array
    {
        $difficulties = DB::table('question_difficulties')->pluck('code', 'id');
        $parts = ExamPart::with(['skill', 'examType'])->where('exam_type_id', $data['exam_type_id'])->get()->keyBy('id');
        $questions = QuestionBankItem::with(['draftRevision', 'publishedRevision', 'learningItem', 'difficulty', 'questionType'])
            ->where(fn ($query) => $query->where(fn ($q) => $q->whereNull('learning_item_id')->where('status', '!=', 'archived'))->orWhereHas('learningItem', fn ($items) => $items->where('status', '!=', 'archived')))
            ->where(fn ($query) => $query->whereNotNull('published_revision_id')
                ->orWhereHas('learningItem', fn ($items) => $items->where('status', 'published'))
                ->orWhere(fn ($q) => $q->whereNull('learning_item_id')->where('status', 'published'))
                ->orWhereHas('draftRevision', fn ($revision) => $revision->where('status', 'approved')->where('kind', 'question')))
            ->get();
        $pool = [];
        $stimulusCache = [];
        foreach ($questions as $question) {
            $revision = $this->revisions->approvedOrPublishedRevision('question', $question);
            if ($revision && ($revision->kind !== 'question' || (int) $revision->entity_id !== (int) $question->id)) {
                continue;
            }
            if (! $revision && ($question->published_revision_id || $question->contentStatus() !== 'published')) {
                continue;
            }
            $payload = $revision?->payload ?? $this->revisions->payload('question', $question, false);
            $part = $parts[(int) ($payload['exam_part_id'] ?? 0)] ?? null;
            $difficulty = $difficulties[$payload['question_difficulty_id'] ?? $question->question_difficulty_id] ?? null;
            if (! $part || ! in_array($difficulty, ['easy', 'medium', 'hard'], true) || ($part->examType->code === 'ielts' && in_array($part->skill->code, ['reading', 'writing'], true) && ($payload['metadata']['variant'] ?? null) !== ($data['variant'] ?? null))) {
                continue;
            }
            try {
                $this->registry->validate($payload);
            } catch (ValidationException) {
                continue;
            }
            $group = ! empty($payload['group_id']) ? DB::table('question_groups')->find($payload['group_id']) : null;
            if (! empty($payload['group_id']) && (! $group || (int) $group->exam_part_id !== $part->id)) {
                continue;
            }
            $stimuli = array_unique(array_merge($payload['stimulus_ids'] ?? [], $group ? json_decode($group->stimulus_ids, true) ?? [] : []));
            $resources = [];
            $passageIds = [];
            foreach ($stimuli as $stimulusId) {
                if (! array_key_exists($stimulusId, $stimulusCache)) {
                    $stimulus = LearningStimulus::find($stimulusId);
                    if (! $stimulus || $stimulus->status === 'archived') {
                        $stimulusCache[$stimulusId] = null;
                    } else {
                        $stimulusRevision = $this->revisions->approvedOrPublishedRevision('stimulus', $stimulus);
                        $stimulusCache[$stimulusId] = $stimulusRevision?->payload ?? $this->revisions->payload('stimulus', $stimulus, true);
                    }
                }
                if (! $stimulusCache[$stimulusId]) {
                    continue 2;
                }
                $resources[] = $stimulusCache[$stimulusId];
                if (($stimulusCache[$stimulusId]['stimulus_type'] ?? null) === 'reading') {
                    $passageIds[] = (int) $stimulusId;
                }
            }
            $item = array_replace($payload, ['key' => 'q'.$question->id, 'stimuli' => $resources]);
            // Reuse publication checks for missing audio, passages, images and files.
            $problems = app(ExamPublishingService::class)->problems(['items' => [$item], 'sections' => [], 'policies' => ExamPublishingService::POLICIES, 'mode' => 'mini', 'exam_type' => $part->examType->code, 'variant' => $data['variant'] ?? null, 'scoring' => null]);
            if ($problems || ! in_array($payload['type'] ?? '', $this->registry->allowedTypes($part->examType->code, $part->skill->code), true)) {
                continue;
            }
            if ($part->examType->code === 'thpt' && count($payload['options'] ?? []) !== 4) {
                continue;
            }
            $pool[$question->id] = ['id' => $question->id, 'part_id' => $part->id, 'difficulty' => $difficulty, 'units' => $this->registry->units($payload), 'group' => $payload['group_id'] ?? null, 'order' => $payload['group_order'] ?? $question->group_order ?? 0, 'passages' => count($passageIds), 'passage_ids' => $passageIds];
        }

        return $pool;
    }

    private function bundles(array $pool, string $template, string $part): array
    {
        $groups = [];
        foreach ($pool as $item) {
            $groups[$item['group'] ? 'g'.$item['group'] : 'q'.$item['id']][] = $item;
        }
        $bundles = [];
        foreach ($groups as $items) {
            $first = $items[0];
            if ($first['group'] && DB::table('question_bank_items')->where('question_group_id', $first['group'])->count() !== count($items)) {
                continue;
            }
            if ($template === 'toeic') {
                $groupSize = ['part-3' => 3, 'part-4' => 3, 'part-6' => 4][$part] ?? null;
                if (array_filter($items, fn ($item) => $item['units'] !== 1) || ($groupSize && (! $first['group'] || count($items) !== $groupSize))) {
                    continue;
                }
                if ($part === 'part-7' && (! $first['group'] || ! in_array($first['passages'], [1, 2, 3], true) || ($first['passages'] === 1 && ! in_array(count($items), [2, 3, 4], true)) || ($first['passages'] > 1 && count($items) !== 5) || array_filter($items, fn ($item) => $item['passages'] !== $first['passages']))) {
                    continue;
                }
            }
            if (str_starts_with($template, 'thpt-english')) {
                $sectionDefinition = collect(self::THPT_ENGLISH_PARTS)->firstWhere('code', $part);
                $bundledTextPart = in_array($part, ['tieng-anh-part-1', 'tieng-anh-part-2', 'tieng-anh-part-4', 'tieng-anh-part-5', 'tieng-anh-part-6'], true);
                $passageIds = array_values(array_unique(array_merge(...array_column($items, 'passage_ids'))));
                $hasSharedPassage = count($passageIds) === 1 && ! array_filter($items, fn ($item) => ! in_array($passageIds[0], $item['passage_ids'], true));
                if (array_filter($items, fn ($item) => $item['units'] !== 1)
                    || ($bundledTextPart && (! $first['group'] || count($items) !== (int) ($sectionDefinition['count'] ?? 0) || ! $hasSharedPassage))) {
                    continue;
                }
            }
            usort($items, fn ($a, $b) => [$a['order'], $a['id']] <=> [$b['order'], $b['id']]);
            $counts = [0, 0, 0, 0];
            foreach ($items as $item) {
                $counts[array_search($item['difficulty'], ['easy', 'medium', 'hard'], true)] += $item['units'];
                if ($template === 'toeic' && $part === 'part-7' && $item['passages'] === 1) {
                    $counts[3] += $item['units'];
                }
            }
            $bundles[] = ['ids' => array_column($items, 'id'), 'counts' => $counts];
        }

        return $bundles;
    }

    /** Sparse subset sum preserves mixed-difficulty groups without greedy shortfalls. */
    private function select(array $bundles, array $target): ?array
    {
        shuffle($bundles);
        $states = ['0,0,0,0' => null];
        $targetKey = implode(',', $target);
        foreach ($bundles as $index => $bundle) {
            foreach (array_keys($states) as $key) {
                $counts = array_map('intval', explode(',', $key));
                $next = [];
                foreach ($counts as $i => $count) {
                    $next[$i] = $count + $bundle['counts'][$i];
                    if ($next[$i] > $target[$i]) {
                        continue 2;
                    }
                }
                $nextKey = implode(',', $next);
                if (! array_key_exists($nextKey, $states)) {
                    $states[$nextKey] = ['previous' => $key, 'bundle' => $index];
                }
                if ($nextKey === $targetKey) {
                    $selected = [];
                    while ($nextKey !== '0,0,0,0') {
                        $state = $states[$nextKey];
                        $selected[] = $bundles[$state['bundle']];
                        $nextKey = $state['previous'];
                    }

                    return array_reverse($selected);
                }
            }
        }

        return null;
    }
}
