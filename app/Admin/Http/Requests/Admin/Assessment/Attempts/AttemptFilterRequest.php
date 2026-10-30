<?php

namespace VistiqAdmin\Http\Requests\Admin\Assessment\Attempts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttemptFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('results.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'user' => 'nullable|integer|exists:users,id', 'exam' => 'nullable|integer|exists:exam_templates,id',
            'status' => 'nullable|in:in_progress,paused,expired,submitted,pending_grading,completed',
            'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'min_score' => 'nullable|numeric|min:0', 'max_score' => ['nullable', 'numeric', 'min:0', Rule::when($this->filled('min_score'), 'gte:min_score')],
            'reports' => 'nullable|in:pending,any,none',
        ];
    }
}
