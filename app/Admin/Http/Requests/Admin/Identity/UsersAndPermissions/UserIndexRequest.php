<?php

namespace VistiqAdmin\Http\Requests\Admin\Identity\UsersAndPermissions;

use Illuminate\Foundation\Http\FormRequest;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:all,active,locked'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
