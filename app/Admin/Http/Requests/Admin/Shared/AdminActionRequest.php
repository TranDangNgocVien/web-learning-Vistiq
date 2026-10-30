<?php

namespace VistiqAdmin\Http\Requests\Admin\Shared;

use Illuminate\Foundation\Http\FormRequest;

class AdminActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['purpose' => 'nullable|in:writing_evaluation,speaking_evaluation,translation,explanation_generation', 'input' => 'nullable|array', 'note' => 'nullable|string|max:2000', 'action' => 'nullable|in:review,review_all,approve,reject,publish,archive,delete_unused', 'draft_signature' => 'nullable|required_if:action,review_all|string|size:64|regex:/\A[a-f0-9]{64}\z/i', 'ids' => 'nullable|array|max:100', 'ids.*' => 'integer|exists:question_bank_items,id', 'matching_ids' => 'nullable|array|max:5000', 'matching_ids.*' => 'integer|exists:question_bank_items,id', 'operation_key' => 'nullable|uuid', 'delete_offset' => 'required_with:operation_key|integer|min:0|max:5000', 'selection_total' => 'required_with:operation_key|integer|min:1|max:5000', 'final_batch' => 'required_with:operation_key|boolean', 'title' => 'nullable|string|max:255', 'kind' => 'nullable|in:conversation,talk,part6,reading_set,ielts_section,question_group', 'exam_part_id' => 'nullable|integer|exists:exam_parts,id', 'stimulus_ids' => 'nullable|array|max:3', 'stimulus_ids.*' => 'integer|exists:learning_stimuli,id', 'metadata' => 'nullable|array', 'permissions' => 'nullable|array|max:100', 'permissions.*' => 'integer|distinct|exists:permissions,id', 'roles' => 'nullable|array', 'roles.*' => 'integer|exists:roles,id', 'plan_id' => 'nullable|integer|exists:subscription_plans,id', 'expires_at' => 'nullable|date', 'leaderboard_status' => 'nullable|in:private,approved,rejected'];
    }
}
