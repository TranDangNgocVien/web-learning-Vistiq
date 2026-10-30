<?php

namespace VistiqAdmin\Http\Requests\Admin\Learning\Knowledge;

use Illuminate\Foundation\Http\FormRequest;

class KnowledgeImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('content.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,tsv|extensions:csv,tsv,txt|max:5120',
            'type' => 'required|in:vocabulary,collocation,phrasal_verb,idiom,grammar',
            'duplicate_policy' => 'sometimes|in:skip,overwrite',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn tệp để import.',
            'file.mimes' => 'Chỉ chấp nhận tệp CSV (.csv).',
            'file.max' => 'Tệp không được vượt quá 5 MB.',
            'type.required' => 'Vui lòng chọn loại kiến thức.',
        ];
    }
}
