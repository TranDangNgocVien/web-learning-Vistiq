<?php

namespace VistiqCore\Services\Assessment\QuestionBank;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use VistiqCore\Models\MediaAsset;

class QuestionRegistry
{
    public const TYPES = [
        'multiple_choice' => 'choice', 'multiple_response' => 'choice', 'true_false_not_given' => 'choice', 'yes_no_not_given' => 'choice',
        'matching' => 'matching', 'matching_information' => 'matching', 'matching_headings' => 'matching', 'matching_features' => 'matching', 'matching_sentence_endings' => 'matching',
        'sentence_completion' => 'text', 'summary_completion' => 'text', 'note_completion' => 'text', 'table_completion' => 'text', 'flow_chart_completion' => 'text', 'form_completion' => 'text',
        'diagram_label_completion' => 'text', 'plan_map_labelling' => 'text', 'short_answer' => 'text', 'essay' => 'writing', 'speaking' => 'speaking',
    ];

    public const WRITING_CRITERIA = ['task_response', 'coherence', 'lexical_resource', 'grammar'];

    public const SPEAKING_CRITERIA = ['fluency', 'lexical_resource', 'grammar', 'pronunciation'];

    /** @return list<string> */
    public function allowedTypes(string $examCode, string $skillCode): array
    {
        if ($examCode === 'toeic') {
            return ['multiple_choice'];
        }
        if ($examCode === 'thpt') {
            return ['multiple_choice'];
        }

        return match ($skillCode) {
            'reading' => [
                'multiple_choice', 'multiple_response', 'true_false_not_given', 'yes_no_not_given',
                'matching', 'matching_information', 'matching_headings', 'matching_features', 'matching_sentence_endings',
                'sentence_completion', 'summary_completion', 'note_completion', 'table_completion', 'flow_chart_completion',
                'diagram_label_completion', 'short_answer',
            ],
            'listening' => [
                'multiple_choice', 'multiple_response', 'matching', 'sentence_completion', 'summary_completion',
                'note_completion', 'table_completion', 'flow_chart_completion', 'form_completion',
                'diagram_label_completion', 'plan_map_labelling', 'short_answer',
            ],
            'writing' => ['essay'],
            'speaking' => ['speaking'],
            default => array_keys(self::TYPES),
        };
    }

    public function sanitize(string $html): string
    {
        $configuration = (new HtmlSanitizerConfig)->allowSafeElements()->allowRelativeMedias()->allowRelativeLinks()->allowMediaSchemes(['https'])->allowLinkSchemes(['https', 'http', 'mailto']);
        foreach (['table', 'thead', 'tbody', 'tr', 'th', 'td'] as $element) {
            $configuration = $configuration->allowElement($element, ['colspan', 'rowspan']);
        }

        return (new HtmlSanitizer($configuration))->sanitize($html);
    }

    public function validate(array $item): void
    {
        Validator::make($item, [
            'options' => 'sometimes|array|max:30', 'options.*' => 'string|max:2000', 'correct_options' => 'sometimes|array|max:30', 'correct_options.*' => 'string|max:12',
            'accepted_answers' => 'sometimes|array|max:50', 'accepted_answers.*' => 'string|max:500', 'metadata' => 'sometimes|array',
            'metadata.answer_map' => 'sometimes|array|max:100', 'metadata.answer_map.*' => 'string|max:30', 'metadata.blanks' => 'sometimes|array|max:100',
            'metadata.blanks.*' => 'array:label,accepted_answers,max_words,max_numbers', 'metadata.blanks.*.label' => 'sometimes|string|max:500',
            'metadata.blanks.*.accepted_answers' => 'required|array|min:1|max:50', 'metadata.blanks.*.accepted_answers.*' => 'string|max:500',
            'metadata.blanks.*.max_words' => 'sometimes|integer|min:0|max:100', 'metadata.blanks.*.max_numbers' => 'sometimes|integer|min:0|max:20',
            'metadata.rubric' => 'sometimes|array', 'metadata.rubric.*' => 'string|max:2000', 'metadata.labels' => 'sometimes|array', 'metadata.labels.*' => 'string|max:1000',
            'metadata.follow_ups' => 'sometimes|array|max:20', 'metadata.follow_ups.*' => 'string|max:2000',
        ])->validate();
        $type = $item['type'] ?? '';
        $family = self::TYPES[$type] ?? null;
        $errors = [];
        if (! $family) {
            $errors['type'] = 'Dạng câu hỏi chưa được hỗ trợ.';
        }
        if (trim(strip_tags($item['prompt'] ?? '')) === '') {
            $errors['prompt'] = 'Câu hỏi cần nội dung.';
        }
        if ($family === 'choice') {
            $options = $item['options'] ?? [];
            $correct = $item['correct_options'] ?? [];
            if (count($options) < 2 || $correct === [] || array_diff($correct, array_keys($options))) {
                $errors['options'] = 'Cần phương án và đáp án đúng hợp lệ.';
            }
            if ($type !== 'multiple_response' && count($correct) !== 1) {
                $errors['correct_options'] = 'Câu một lựa chọn cần đúng một đáp án.';
            }
            if ($type === 'true_false_not_given' && array_keys($options) !== ['TRUE', 'FALSE', 'NOT_GIVEN']) {
                $errors['options'] = 'Dùng TRUE, FALSE, NOT_GIVEN.';
            }
            if ($type === 'yes_no_not_given' && array_keys($options) !== ['YES', 'NO', 'NOT_GIVEN']) {
                $errors['options'] = 'Dùng YES, NO, NOT_GIVEN.';
            }
            if (($item['exam_type'] ?? '') === 'toeic' && count($options) !== (($item['part'] ?? '') === 'part-2' ? 3 : 4)) {
                $errors['options'] = 'TOEIC cần 3 lựa chọn ở Part 2, 4 lựa chọn ở các Part khác.';
            }
        }
        if ($family === 'text' && empty($item['accepted_answers']) && empty($item['metadata']['blanks'])) {
            $errors['accepted_answers'] = 'Nhập ít nhất một đáp án chấp nhận.';
        }
        if (($item['exam_type'] ?? '') === 'toeic' && $type !== 'multiple_choice') {
            $errors['type'] = 'TOEIC Listening/Reading chỉ dùng Multiple Choice.';
        }
        if (($item['exam_type'] ?? '') === 'thpt' && ($type !== 'multiple_choice' || array_keys($item['options'] ?? []) !== ['A', 'B', 'C', 'D'])) {
            $errors['options'] = 'Câu Tiếng Anh THPT cần đúng bốn lựa chọn A, B, C, D.';
        }
        foreach ($item['metadata']['blanks'] ?? [] as $key => $blank) {
            if (empty($blank['accepted_answers']) || ! is_array($blank['accepted_answers'])) {
                $errors['blanks.'.$key] = 'Mỗi ô cần đáp án chấp nhận.';
            }
        }
        if ($family === 'matching') {
            $mapping = $item['metadata']['answer_map'] ?? [];
            if ($mapping === [] || array_diff(array_values($mapping), array_keys($item['options'] ?? []))) {
                $errors['answer_map'] = 'Mapping cần đáp án tồn tại trong danh sách lựa chọn.';
            }
        }
        if (in_array($family, ['writing', 'speaking'], true) && empty($item['metadata']['rubric'])) {
            $errors['rubric'] = 'Writing/Speaking cần rubric.';
        }
        if (in_array($family, ['writing', 'speaking'], true)) {
            $criteria = $family === 'writing' ? self::WRITING_CRITERIA : self::SPEAKING_CRITERIA;
            if (! is_array($item['metadata']['rubric'] ?? null) || array_diff($criteria, array_keys($item['metadata']['rubric'] ?? []))) {
                $errors['rubric'] = 'Rubric cần đủ bốn tiêu chí.';
            }
        }
        if (($item['skill'] ?? '') === 'writing' && $family !== 'writing' || ($item['skill'] ?? '') === 'speaking' && $family !== 'speaking' || in_array($item['skill'] ?? '', ['listening', 'reading'], true) && in_array($family, ['writing', 'speaking'], true)) {
            $errors['type'] = 'Dạng câu hỏi không phù hợp skill.';
        }
        $metadata = Validator::make($item['metadata'] ?? [], [
            'max_words' => 'nullable|integer|min:0|max:100', 'max_numbers' => 'nullable|integer|min:0|max:20', 'min_words' => 'nullable|integer|min:1|max:10000',
            'image_id' => 'nullable|integer|exists:media_assets,id', 'audio_id' => 'nullable|integer|exists:media_assets,id', 'variant' => 'nullable|in:academic,general',
            'accepted_answers' => 'nullable|array', 'answer_map' => 'nullable|array', 'cue_card' => 'nullable|string|max:10000', 'follow_ups' => 'nullable|array|max:20',
        ]);
        if (in_array($type, ['diagram_label_completion', 'plan_map_labelling'], true) && empty($item['metadata']['image_id'])) {
            $errors['image_id'] = 'Diagram/map cần ảnh.';
        }
        foreach (['image_id' => 'image/', 'audio_id' => 'audio/'] as $field => $prefix) {
            if (! empty($item['metadata'][$field]) && ($asset = MediaAsset::find($item['metadata'][$field])) && ! str_starts_with($asset->mime_type, $prefix)) {
                $errors[$field] = 'Media không đúng loại.';
            }
        }
        if ($metadata->fails()) {
            $errors = array_merge($errors, $metadata->errors()->toArray());
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function units(array $item): int
    {
        if ((self::TYPES[$item['type']] ?? '') === 'matching') {
            return count($item['metadata']['answer_map'] ?? []);
        }
        if (($item['type'] ?? '') === 'multiple_response') {
            return count($item['correct_options'] ?? []);
        }

        return count($item['metadata']['blanks'] ?? []) ?: 1;
    }

    public function publicItem(array $item, bool $review = false): array
    {
        if (($item['type'] ?? '') === 'multiple_response') {
            $item['selection_count'] = count($item['correct_options'] ?? []);
        }
        $item['matching_rows'] = array_keys($item['metadata']['answer_map'] ?? []);
        unset($item['correct_options'],$item['accepted_answers']);
        $metadata = array_intersect_key($item['metadata'] ?? [], array_flip(['max_words', 'max_numbers', 'min_words', 'image_id', 'audio_id', 'variant', 'cue_card', 'follow_ups', 'rubric', 'labels', 'paragraph_labels', 'source', 'speaker_labels']));
        $item['blank_rows'] = array_map(fn (array $blank): array => ['label' => $blank['label'] ?? ''], $item['metadata']['blanks'] ?? []);
        $item['metadata'] = $metadata;
        if (is_array($item['metadata']['rubric'] ?? null)) {
            $item['metadata']['rubric'] = array_intersect_key($item['metadata']['rubric'], array_flip(array_merge(self::WRITING_CRITERIA, self::SPEAKING_CRITERIA)));
        }
        if (! $review) {
            unset($item['explanation'],$item['explanation_en'],$item['explanation_vi']);
        }
        foreach ($item['stimuli'] ?? [] as $index => $stimulus) {
            unset($stimulus['transcript']);
            $stimulus['metadata'] = array_intersect_key($stimulus['metadata'] ?? [], array_flip(['asset_id', 'source', 'speaker_labels']));
            $item['stimuli'][$index] = $stimulus;
        }
        if (isset($item['group'])) {
            $item['group'] = array_intersect_key($item['group'] ?? [], array_flip(['id', 'title', 'kind']));
        }
        if (($item['exam_type'] ?? '') === 'toeic' && in_array($item['part'] ?? '', ['part-1', 'part-2'], true)) {
            $item['options'] = array_combine(array_keys($item['options']), array_keys($item['options']));
        }

        return $item;
    }
}
