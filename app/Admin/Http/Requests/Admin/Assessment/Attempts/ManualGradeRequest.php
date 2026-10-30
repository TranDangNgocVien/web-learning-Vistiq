<?php

namespace VistiqAdmin\Http\Requests\Admin\Assessment\Attempts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ManualGradeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('results.grade') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'criteria' => 'required|array|size:4', 'criteria.*' => 'required|numeric|min:0|max:9',
            'criterion_feedback' => 'sometimes|array', 'criterion_feedback.*' => 'nullable|string|max:2000',
            'improvements' => 'nullable|string|max:10000', 'feedback' => 'required|string|max:20000',
            'reason' => ($this->route('answer')?->grade ? 'required' : 'nullable').'|string|max:2000',
            'grade_token' => ($this->route('answer')?->grade ? 'required' : 'nullable').'|string|size:64',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }
}
