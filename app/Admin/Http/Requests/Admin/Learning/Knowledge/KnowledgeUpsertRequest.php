<?php

namespace VistiqAdmin\Http\Requests\Admin\Learning\Knowledge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use VistiqCore\Services\Knowledge\KnowledgeInput;

class KnowledgeUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('content.edit') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('english'))) {
            $this->merge(['english' => trim($this->input('english'))]);
        }
        if ($this->has('dictionary_entry_ids_text') && ! $this->has('dictionary_entry_ids')) {
            $this->merge(['dictionary_entry_ids' => preg_split('/[\\s,]+/', trim($this->input('dictionary_entry_ids_text') ?? ''), -1, PREG_SPLIT_NO_EMPTY)]);
        }

        foreach (['dictionary_entry_ids', 'examples', 'relations'] as $field) {
            if ($this->boolean($field.'_present') && ! $this->has($field)) {
                $this->merge([$field => []]);
            }
        }

        foreach (['examples', 'relations', 'grammar_rules'] as $field) {
            if (! $this->has($field) && $this->filled($field.'_json')) {
                $data = json_decode($this->input($field.'_json'), true);
                if (! is_array($data)) {
                    throw ValidationException::withMessages([$field => 'JSON không hợp lệ.']);
                }

                $this->merge([$field => $data]);
            }

            $rows = $this->input($field);
            if (is_array($rows)) {
                $this->merge([$field => array_values(array_filter($rows, function (mixed $row) use ($field): bool {
                    if (! is_array($row)) {
                        return false;
                    }

                    return match ($field) {
                        'examples' => filled($row['english'] ?? null) || filled($row['vietnamese'] ?? null),
                        'relations' => filled($row['id'] ?? null) && filled($row['type'] ?? null),
                        'grammar_rules' => filled($row['title'] ?? null) || filled($row['formula'] ?? null) || filled($row['explanation'] ?? null),
                        default => true,
                    };
                }))]);
            }
        }
    }

    public function rules(): array
    {
        return KnowledgeInput::rules();
    }
}
