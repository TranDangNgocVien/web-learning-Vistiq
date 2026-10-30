<?php

namespace VistiqAdmin\Http\Requests\Admin\Assessment\ContentApproval;

use Illuminate\Foundation\Http\FormRequest;

class ReviewDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('content.review') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('note'))) {
            $this->merge(['note' => trim($this->input('note'))]);
        }
    }

    public function rules(): array
    {
        return [
            'revision_counter' => 'required|integer|min:1',
            'review_token' => 'required|string|size:64',
            'note' => ($this->route('action') === 'reject' ? 'required' : 'nullable').'|string|max:2000',
        ];
    }
}
