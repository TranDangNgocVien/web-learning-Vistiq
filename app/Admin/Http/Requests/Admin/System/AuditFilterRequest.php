<?php

namespace VistiqAdmin\Http\Requests\Admin\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('audit.view') ?? false;
    }

    public function rules(): array
    {
        return ['actor' => 'nullable|integer|min:1', 'action' => 'nullable|string|max:80', 'entity' => 'nullable|string|max:80', 'entity_id' => 'nullable|integer|min:1', 'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')], 'result' => 'nullable|in:success,failure,pending'];
    }
}
