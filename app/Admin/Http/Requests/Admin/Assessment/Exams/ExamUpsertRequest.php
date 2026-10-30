<?php

namespace VistiqAdmin\Http\Requests\Admin\Assessment\Exams;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;

class ExamUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('content.edit') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['sections', 'policies', 'generation'] as $key) {
            if ($this->has($key.'_json')) {
                if ($key === 'generation' && ! $this->filled($key.'_json')) {
                    $this->merge(['generation' => null]);

                    continue;
                }
                $data = json_decode($this->input($key.'_json'), true);
                if (! is_array($data)) {
                    throw ValidationException::withMessages([$key => 'JSON không hợp lệ.']);
                } $this->merge([$key => $data]);
            }
        }
    }

    public function rules(): array
    {
        $rules = ['title' => 'required|string|max:180', 'description' => 'nullable|string|max:10000', 'provider' => 'nullable|string|max:120', 'exam_type_id' => 'required|integer|exists:exam_types,id', 'mode' => 'required|in:full,mini,skill', 'variant' => 'nullable|in:academic,general', 'visual_template' => 'nullable|in:toeic-full,toeic-mini,toeic-part5,toeic-part7,toeic-listening,toeic-reading,ielts-academic,ielts-general,thpt,thpt-english,thpt-english-2025,thpt-english-2026,custom', 'score_scale_id' => 'nullable|integer|exists:score_scales,id', 'opens_at' => 'nullable|date', 'closes_at' => 'nullable|date|after:opens_at', 'revision_counter' => 'nullable|integer|min:0', 'sections' => 'present|array|max:20', 'sections.*.exam_part_id' => 'required|integer|exists:exam_parts,id', 'sections.*.question_ids' => 'present|array|max:200', 'sections.*.question_ids.*' => 'integer|exists:question_bank_items,id', 'sections.*.duration_minutes' => 'nullable|integer|min:1|max:240', 'sections.*.target_count' => 'sometimes|integer|min:1|max:200', 'policies' => 'present|array:'.implode(',', array_keys(ExamPublishingService::POLICIES)), 'policies.duration_minutes' => 'required|integer|min:1|max:480', 'policies.navigation' => 'nullable|in:free,linear', 'policies.answer_review' => 'nullable|in:after_each,after_submit,never,after_close', 'policies.explanation_review' => 'nullable|in:after_each,after_submit,never,after_close', 'policies.warning_threshold' => 'nullable|integer|min:0|max:100', 'policies.auto_submit_threshold' => 'nullable|integer|min:0|max:100'];
        foreach (ExamPublishingService::POLICIES as $key => $value) {
            if (is_bool($value)) {
                $rules['policies.'.$key] = 'sometimes|boolean';
            }
        }

        return array_merge($rules, ExamGenerationRequest::generationRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $totalMinutes = (int) data_get($this->input('policies'), 'duration_minutes', 0);
            $sectionMinutes = collect($this->input('sections', []))->sum(
                fn ($section) => is_array($section) ? max(0, (int) ($section['duration_minutes'] ?? 0)) : 0,
            );

            $template = (string) data_get($this->input('generation'), 'template', '');
            if (str_starts_with($template, 'thpt-english') && $totalMinutes !== 50) {
                $validator->errors()->add('policies.duration_minutes', 'Đề Tiếng Anh THPT có thời gian làm bài cố định 50 phút.');
            }

            if ($sectionMinutes > $totalMinutes) {
                $validator->errors()->add('sections', 'Tổng thời gian các phần không được vượt thời gian làm bài của đề.');
            }
        });
    }
}
