<?php

namespace VistiqLearner\Http\Requests\Assessment\ExamTakingAndSubmission;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttemptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answers' => 'sometimes|array|max:200',
            'answers.*.item_key' => 'required|string|max:80|distinct',
            'answers.*.response' => 'required|array',
            'answers.*.version' => 'required|integer|min:0',
            'answers.*.spent_seconds' => 'sometimes|integer|min:0|max:86400',
            'answers.*.flagged' => 'sometimes|boolean',
            'type' => 'sometimes|in:FULLSCREEN_EXIT,TAB_HIDDEN,WINDOW_BLUR,COPY_ATTEMPT,PASTE_ATTEMPT,CONTEXT_MENU,RELOAD,NETWORK_DISCONNECT,MULTIPLE_SESSION',
            'event_key' => 'required_with:type|string|max:80',
            'item_key' => 'sometimes|string|max:80',
            'stimulus_id' => 'sometimes|nullable|integer',
            'file' => 'sometimes|required|file|mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/wav,audio/x-wav,audio/mp4,video/webm|max:51200',
        ];
    }
}
