<?php

namespace VistiqAdmin\Http\Requests\Admin\Identity\UsersAndPermissions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use VistiqCore\Models\Permission;
use VistiqCore\Models\Role;
use VistiqCore\Support\PermissionCatalog;

class RoleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage') ?? false;
    }

    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->ignore($role->id),
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
            /** @var Role $role */
            $role = $this->route('role');

            if ($role && in_array($role->code, ['admin', 'super-admin'], true) && $this->has('permissions')) {
                $validator->errors()->add('permissions', $role->name.' luôn có toàn bộ quyền và không thể chỉnh sửa.');

                return;
            }

            if (! $this->has('permissions')) {
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
        if ($this->boolean('permission_matrix') && ! $this->has('permissions')) {
            $this->merge(['permissions' => []]);
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
