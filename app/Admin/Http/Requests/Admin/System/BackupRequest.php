<?php

namespace VistiqAdmin\Http\Requests\Admin\System;

use Illuminate\Foundation\Http\FormRequest;

class BackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return ['password' => 'required|current_password:admin'];
    }
}
