<?php

namespace VistiqAdmin\Http\Requests\Admin\Identity\UsersAndPermissions;

use Illuminate\Foundation\Http\FormRequest;

class UserActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission($this->route('action') === 'roles' ? 'roles.manage' : 'users.manage') ?? false;
    }

    public function rules(): array
    {
        return ['roles' => ($this->route('action') === 'roles' ? 'present|array' : 'sometimes|array'), 'roles.*' => 'integer|exists:roles,id', 'plan_id' => 'sometimes|integer|exists:subscription_plans,id', 'expires_at' => 'nullable|date|after:now'];
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('action') === 'roles' && ! $this->has('roles')) {
            $this->merge(['roles' => []]);
        }
    }
}
