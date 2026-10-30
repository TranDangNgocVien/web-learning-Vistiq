<?php

namespace VistiqAdmin\Http\Requests\Admin\Assessment\Exams;

use Illuminate\Foundation\Http\FormRequest;
use VistiqCore\Services\Assessment\Exams\ExamGenerationService;

class ExamGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('content.edit') ?? false;
    }

    public static function generationRules(): array
    {
        return [
            'generation' => 'nullable|array:template,sections',
            'generation.template' => 'required_with:generation|in:'.implode(',', ExamGenerationService::TEMPLATES),
            'generation.sections' => 'required_with:generation|array|min:1|max:20',
            'generation.sections.*' => 'array:exam_part_id,difficulty,count,duration_minutes',
            'generation.sections.*.exam_part_id' => 'required|integer|exists:exam_parts,id',
            'generation.sections.*.duration_minutes' => 'nullable|integer|min:1|max:240',
            'generation.sections.*.count' => 'sometimes|integer|min:1|max:200',
            'generation.sections.*.difficulty' => 'required|array:easy,medium,hard',
            'generation.sections.*.difficulty.easy' => 'required|integer|min:0|max:200',
            'generation.sections.*.difficulty.medium' => 'required|integer|min:0|max:200',
            'generation.sections.*.difficulty.hard' => 'required|integer|min:0|max:200',
        ];
    }

    public function rules(): array
    {
        return array_replace(self::generationRules(), [
            'exam_type_id' => 'required|integer|exists:exam_types,id',
            'variant' => 'nullable|in:academic,general',
            'generation' => 'required|array:template,sections',
        ]);
    }
}
