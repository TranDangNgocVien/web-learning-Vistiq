<?php

namespace VistiqAdmin\Http\Requests\Admin\Assessment\ContentApproval;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('content.review') ?? false;
    }

    public function rules(): array
    {
        return [
            'selection_mode' => 'required|in:all_filtered,selected',
            'expected_count' => 'required|integer|min:1',
            'selection_snapshot' => 'required|string|size:64',
            'selected' => 'required_if:selection_mode,selected|array|min:1|max:500',
            'selected.*' => ['required', 'string', 'distinct', 'regex:/^(question|stimulus|exam):[1-9][0-9]*$/'],
            'excluded' => 'sometimes|array|max:500',
            'excluded.*' => ['required', 'string', 'distinct', 'regex:/^(question|stimulus|exam):[1-9][0-9]*$/'],
            'kind' => 'nullable|in:question,stimulus,exam',
            'q' => 'nullable|string|max:255',
            'author' => 'nullable|integer|exists:users,id',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'revision' => 'nullable|integer|min:1',
            'exam_type' => 'nullable|integer|exists:exam_types,id',
            'part' => 'nullable|integer|exists:exam_parts,id',
        ];
    }
}
