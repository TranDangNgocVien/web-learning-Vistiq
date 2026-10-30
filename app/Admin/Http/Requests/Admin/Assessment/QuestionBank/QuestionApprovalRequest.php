<?php

namespace VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank;

use Illuminate\Foundation\Http\FormRequest;

class QuestionApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:question_bank_items,id'],
            'revision_ids' => ['required', 'array'],
            'revision_ids.*' => ['required', 'integer', 'distinct', 'exists:content_revisions,id'],
        ];
    }
}
