<?php

namespace VistiqLearner\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HistoryFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return ['exam' => 'nullable|integer|min:1', 'status' => 'nullable|in:in_progress,paused,expired,submitted,pending_grading,completed',
            'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')]];
    }
}
