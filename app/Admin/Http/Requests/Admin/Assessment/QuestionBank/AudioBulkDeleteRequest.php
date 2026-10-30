<?php

namespace VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank;

use Illuminate\Foundation\Http\FormRequest;

class AudioBulkDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('content.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required_without:select_all', 'nullable', 'array', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:learning_stimuli,id'],
            'select_all' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:255'],
        ];
    }
}
