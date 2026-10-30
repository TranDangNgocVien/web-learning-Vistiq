<?php

namespace VistiqLearner\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;

class KnowledgeFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:80',
            'level' => 'nullable|string|max:30',
            'type' => 'nullable|string|in:vocabulary,collocation,phrasal_verb,idiom,grammar,all',
            'sort' => 'nullable|in:newest,oldest,alphabetical',
            'collection' => 'nullable|integer|min:1',
            'item' => 'nullable|string|max:200',
            'detail' => 'nullable|in:hidden',
        ];
    }
}
