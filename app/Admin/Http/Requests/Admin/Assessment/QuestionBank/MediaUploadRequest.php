<?php

namespace VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank;

use Illuminate\Foundation\Http\FormRequest;
use VistiqCore\Services\System\SettingsService;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('content.edit') ?? false;
    }

    public function rules(): array
    {
        $libraryRules = ['create_stimulus' => 'nullable|boolean'];

        return [...$libraryRules, 'title' => 'required|string|max:255', 'file' => ['required', 'file', 'max:'.((app(SettingsService::class)->get('media')['max_megabytes'] ?? 50) * 1024), 'mimes:mp3,wav,m4a,ogg,jpg,jpeg,png,webp'], 'question_id' => 'nullable|integer|exists:question_bank_items,id', 'question_group_id' => 'nullable|integer|exists:question_groups,id', 'source' => 'nullable|string|max:1000', 'transcript' => 'nullable|string|max:50000', 'speaker_labels' => 'nullable|string|max:2000'];
    }
}
