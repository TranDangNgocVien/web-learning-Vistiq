<?php

namespace VistiqAdmin\Http\Requests\Admin\Identity\UsersAndPermissions;

use Illuminate\Foundation\Http\FormRequest;
use VistiqCore\Models\Permission;
use VistiqCore\Support\PermissionCatalog;

class RoleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[a-z0-9\-]+$/',
                'unique:roles,code',
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                'unique:roles,name',
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
            ],
            'permissions' => [
                'sometimes',
                'array',
            ],
            'permissions.*' => [
                'integer',
                'exists:permissions,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Mã vai trò không được để trống.',
            'code.regex' => 'Mã vai trò chỉ gồm chữ thường, chữ số và dấu gạch ngang.',
            'code.unique' => 'Mã vai trò đã tồn tại.',
            'name.required' => 'Tên vai trò không được để trống.',
            'name.unique' => 'Tên vai trò đã tồn tại.',
            'permissions.*.exists' => 'Quyền được chọn không tồn tại.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->has('permissions') || ! is_array($this->input('permissions', []))) {
                return;
            }
            $permissionIds = array_map('intval', $this->input('permissions', []));
            if ($permissionIds === []) {
                return;
            }

            $catalogIds = Permission::query()
                ->whereIn('code', PermissionCatalog::codes())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if (array_diff($permissionIds, $catalogIds) !== []) {
                $validator->errors()->add('permissions', 'Chỉ được gán quyền có trong danh mục hệ thống.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => mb_strtolower(trim((string) $this->input('code'))),
            ]);
        }
        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }
        if ($this->has('description')) {
            $this->merge([
                'description' => trim((string) $this->input('description')),
            ]);
        }
    }
}
