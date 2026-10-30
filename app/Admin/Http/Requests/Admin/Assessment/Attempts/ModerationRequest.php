<?php

namespace VistiqAdmin\Http\Requests\Admin\Assessment\Attempts;

use Illuminate\Foundation\Http\FormRequest;

class ModerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('reports.review') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('note'))) {
            $this->merge(['note' => trim($this->input('note'))]);
        }
    }

    public function rules(): array
    {
        return ['leaderboard_status' => 'required|in:private,approved,rejected', 'note' => 'required|string|max:2000'];
    }
}
