<?php

namespace VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank;

use Illuminate\Foundation\Http\FormRequest;
use VistiqCore\Services\System\SettingsService;

class AudioBatchUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('content.edit') ?? false;
    }

    public function rules(): array
    {
        $maxMegabytes = (int) (app(SettingsService::class)->get('media')['max_megabytes'] ?? 50);

        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'max:'.($maxMegabytes * 1024), 'mimes:mp3,wav,m4a,ogg'],
        ];
    }
}
