<?php

namespace VistiqAdmin\Http\Requests\Admin\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class AnalyticsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('results.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'exam_id' => 'nullable|integer|exists:exam_templates,id',
            'mode' => 'nullable|string|in:all,exam,practice',
            'exam_type' => 'nullable|string|exists:exam_types,code',
            'skill' => 'nullable|string|exists:skills,code',
            'status' => 'nullable|in:in_progress,paused,expired,submitted,pending_grading,completed',
            'format' => 'nullable|in:csv,xlsx',
        ];
    }

    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
            'exam_id.exists' => 'Kỳ thi được chọn không tồn tại.',
            'mode.in' => 'Chế độ không hợp lệ (chỉ chấp nhận: all, exam, practice).',
        ];
    }
}
