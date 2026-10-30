<?php

namespace VistiqAdmin\Http\Requests\Admin\System;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use VistiqCore\Services\System\SettingsSchema;

class SettingsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schema = SettingsSchema::RULES[$this->route('group')] ?? [];
        abort_unless($schema, 404);
        $rules = ['typed_form' => 'sometimes|boolean', 'field_count' => 'required_if:typed_form,1|integer|min:1', 'value' => 'required|array:'.implode(',', array_keys(array_filter($schema, fn ($key) => ! str_contains($key, '.'), ARRAY_FILTER_USE_KEY)))];
        foreach ($schema as $key => $rule) {
            $rules['value.'.$key] = $rule;
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $count = 0;
            $value = $this->input('value', []);
            if (! is_array($value)) {
                return;
            }
            array_walk_recursive($value, function () use (&$count) {
                $count++;
            });
            if ($this->filled('field_count') && (int) $this->input('field_count') !== $count) {
                $validator->errors()->add('value', 'Biểu mẫu bị thiếu trường. Giảm kích thước cấu hình hoặc tăng max_input_vars rồi thử lại.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('value_json')) {
            $value = json_decode($this->input('value_json'), true);
            if (! is_array($value)) {
                throw ValidationException::withMessages(['value' => 'Cấu hình phải là JSON object.']);
            } $this->merge(['value' => $value]);
        }
    }
}
