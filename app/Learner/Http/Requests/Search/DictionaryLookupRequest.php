<?php

namespace VistiqLearner\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use VistiqLearner\Models\LearningItem;

class DictionaryLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim(preg_replace('/\\s+/u', ' ', $this->input('q')) ?? '')]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => [$this->routeIs('api.dictionary.index', 'api.dictionary.suggestions') ? 'required' : 'nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', Rule::in(['all', ...LearningItem::LOOKUP_TYPES])],
            'tab' => ['nullable', 'string', Rule::in(['overview', 'examples', 'collocations', 'phrasal-verbs', 'synonyms', 'antonyms', 'exercises'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.required' => 'Vui lòng nhập từ hoặc cụm từ cần tra cứu.',
            'q.string' => 'Từ cần tra cứu phải là chuỗi văn bản.',
            'q.max' => 'Từ cần tra cứu không được vượt quá 100 ký tự.',
            'type.in' => 'Loại nội dung tra cứu không hợp lệ.',
            'type.string' => 'Loại nội dung tra cứu phải là chuỗi văn bản.',
            'tab.in' => 'Nội dung mục từ không hợp lệ.',
            'tab.string' => 'Nội dung mục từ không hợp lệ.',
            'page.integer' => 'Trang phải là số nguyên.',
            'page.min' => 'Trang phải từ 1 trở lên.',
            'page.max' => 'Trang không được vượt quá 1000.',
            'per_page.integer' => 'Số kết quả mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số kết quả mỗi trang phải từ 1 trở lên.',
            'per_page.max' => 'Mỗi trang chỉ được hiển thị tối đa 50 kết quả.',
        ];
    }
}
