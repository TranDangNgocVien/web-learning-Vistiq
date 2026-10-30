<?php

namespace VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank;

use Illuminate\Foundation\Http\FormRequest;

class ReadingReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('content.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:learning_stimuli,id'],
            'revision_ids' => ['required', 'array'],
            'revision_ids.*' => ['required', 'integer'],
        ];
    }
}
