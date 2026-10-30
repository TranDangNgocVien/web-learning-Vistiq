<?php

namespace VistiqAdmin\Http\Requests\Admin\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class LeaderboardFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('results.view') ?? false;
    }

    public function rules(): array
    {
        return ['exam_id' => 'nullable|integer|exists:exam_templates,id', 'format' => 'nullable|in:csv,xlsx'];
    }
}
