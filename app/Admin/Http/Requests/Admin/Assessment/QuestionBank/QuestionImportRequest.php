<?php

namespace VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class QuestionImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('content.edit') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'default_question_type' => ['required', 'integer', 'exists:question_types,id'],
            'default_difficulty' => ['required', 'integer', 'exists:question_difficulties,id'],
            'default_status' => ['required', 'string', 'in:draft'],
            'import_key' => ['nullable', 'uuid'],
            'offset' => ['required', 'integer', 'min:0', 'max:2000'],
            'return_to' => ['nullable', 'in:questions,readings,thpt'],
            'thpt_images' => ['sometimes', 'array', 'max:50'],
            'thpt_images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
            'thpt_image_refs' => ['sometimes', 'array', 'max:50'],
            'thpt_image_refs.*.exam_part' => ['required', 'string', 'in:tieng-anh-part-1,tieng-anh-part-2'],
            'thpt_image_refs.*.reading_key' => ['required', 'string', 'max:100'],
        ];
    }
}
