<?php

namespace VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StimulusStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('content.edit') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        $metadata = $this->filled('metadata_json') ? json_decode($this->input('metadata_json'), true) : ($this->input('metadata', []) ?: []);
        if (! is_array($metadata)) {
            throw ValidationException::withMessages(['metadata' => 'JSON metadata không hợp lệ.']);
        }

        foreach (['source', 'speaker_labels'] as $field) {
            if ($this->has($field)) {
                $value = trim((string) $this->input($field));
                if ($value === '') {
                    unset($metadata[$field]);
                } else {
                    $metadata[$field] = $value;
                }
            }
        }

        if ($this->filled('exam_part_id')) {
            $metadata['exam_part_id'] = $this->integer('exam_part_id');
            $part = \VistiqCore\Models\ExamPart::with('examType')->find($this->integer('exam_part_id'));
            $metadata['content_kind'] = $part?->examType?->code === 'toeic' && $part?->code === 'part-6' ? 'paragraph' : 'passage';
        }
        $this->merge(['metadata' => $metadata]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty() || !$this->filled('metadata.exam_part_id')) return;
            $part = \VistiqCore\Models\ExamPart::with('skill')->find($this->integer('metadata.exam_part_id'));
            if ($this->input('stimulus_type') === 'reading' && $part?->skill?->code !== 'reading') $validator->errors()->add('exam_part_id', 'Chọn phần đọc hiểu cho bài đọc.');
        });
    }

    public function rules(): array
    {
        $mediaRules = $this->input('stimulus_type') === 'audio'
            ? ['nullable', 'file', 'max:51200', 'mimes:mp3,wav,m4a,ogg']
            : ['nullable', 'file', 'max:51200', 'mimes:jpg,jpeg,png,webp'];

        return [
            'stimulus_type' => ['required', 'string', 'in:reading,audio'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:50000'],
            'transcript' => ['nullable', 'string', 'max:50000'],
            'asset_path' => ['nullable', 'string', 'url', 'max:2048'],
            'media' => $mediaRules,
            'accent' => ['nullable', 'string', 'max:40'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'status' => ['nullable', 'in:draft'],
            'exam_part_id' => ['nullable', 'integer', 'exists:exam_parts,id'],
            'metadata.exam_part_id' => ['nullable', 'integer', 'exists:exam_parts,id'],
            'metadata.content_kind' => ['nullable', 'in:passage,paragraph'],
            'metadata' => 'nullable|array', 'metadata.asset_id' => 'nullable|integer|exists:media_assets,id',
            'source' => ['nullable', 'string', 'max:1000'], 'speaker_labels' => ['nullable', 'string', 'max:2000'],
            'revision_counter' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
