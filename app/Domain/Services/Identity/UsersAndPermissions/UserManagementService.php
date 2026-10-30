<?php

namespace VistiqCore\Services\Identity\UsersAndPermissions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use VistiqCore\Models\Permission;
use VistiqCore\Models\Role;
use VistiqCore\Models\User;
use VistiqCore\Services\System\AuditService;
use VistiqCore\Services\System\SettingsService;
use VistiqCore\Support\PermissionCatalog;
use VistiqLearner\Http\Controllers\Authentication\Mail\ResetPasswordEmail;

class UserManagementService
{
    public function __construct(private AuditService $audit) {}

    public function action(User $user, string $action, array $data): void
    {
        DB::transaction(function () use ($user, $action, $data): void {
            $superIds = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')->where('roles.code', 'super-admin')->pluck('user_id');
            $supers = User::whereIn('id', $superIds)->orderBy('id')->lockForUpdate()->get();
            $user = User::lockForUpdate()->findOrFail($user->id);
            $removing = $action === 'lock' || ($action === 'roles' && ! in_array((int) Role::where('code', 'super-admin')->value('id'), array_map('intval', $data['roles'] ?? []), true));
            abort_if($removing && $user->hasRole('super-admin') && $user->status === 'active' && $supers->where('status', 'active')->count() <= 1, 409, 'Không thể khóa hoặc hạ quyền Super Admin cuối cùng.');
            abort_if($action === 'lock' && (int) $user->id === (int) auth()->id(), 409, 'Không thể khóa tài khoản đang sử dụng.');
            $before = ['status' => $user->status, 'roles' => $user->roles()->pluck('code')->all()];
            switch ($action) {
                case 'lock':
                    $user->status = 'locked';
                    break;
                case 'unlock':
                    $user->status = 'active';
                    break;
                case 'revoke-sessions':
                    break;
                case 'roles':
                    $user->roles()->sync($data['roles'] ?? []);
                    break;
                case 'password-reset':
                    try {
                        app(SettingsService::class)->configureMail();
                        if (in_array(config('mail.default'), ['log', 'array'], true) && ! app()->environment('testing')) {
                            throw ValidationException::withMessages(['email' => 'Dịch vụ email chưa sẵn sàng. Hãy cấu hình gửi email trước khi thử lại.']);
                        }
                        ResetPasswordEmail::configure();
                        $status = Password::sendResetLink(['email' => $user->email]);
                    } catch (TransportExceptionInterface $exception) {
                        throw ValidationException::withMessages(['email' => 'Chưa thể gửi email đặt lại mật khẩu. Vui lòng thử lại sau ít phút.']);
                    }
                    if ($status !== Password::RESET_LINK_SENT) {
                        throw ValidationException::withMessages(['email' => $status === Password::RESET_THROTTLED ? 'Vừa gửi yêu cầu cho tài khoản này. Hãy đợi 60 giây trước khi gửi lại.' : 'Không thể gửi liên kết cho tài khoản này. Vui lòng kiểm tra email.']);
                    }
                    break;
                case 'subscription':
                    abort(410, 'Chức năng mua gói đã ngừng sử dụng.');
                default:
                    abort(404);
            }
            if (in_array($action, ['lock', 'roles', 'revoke-sessions'], true)) {
                $user->session_version++;
                $user->setRememberToken(Str::random(60));
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            $user->save();
            $this->audit->record('user.'.$action, 'user', $user->id, $before, ['status' => $user->status, 'roles' => $user->roles()->pluck('code')->all()]);
        });
    }

    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = Role::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            if (isset($data['permissions']) && is_array($data['permissions'])) {
                $permissionIds = array_map('intval', $data['permissions']);
                $catalogIds = Permission::query()
                    ->whereIn('code', PermissionCatalog::codes())
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                abort_if(array_diff($permissionIds, $catalogIds) !== [], 422, 'Chỉ được gán quyền có trong danh mục hệ thống.');
                $role->permissions()->sync($permissionIds);
            }

            $this->audit->record(
                'role.create',
                'role',
                $role->id,
                [],
                [
                    'code' => $role->code,
                    'name' => $role->name,
                    'description' => $role->description,
                    'permissions' => $role->permissions()->pluck('code')->all(),
                ]
            );

            return $role;
        });
    }

    public function updateRole(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            $before = [
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $role->permissions()->pluck('code')->all(),
                'affected_users_count' => $role->users()->count(),
            ];

            if (in_array($role->code, ['admin', 'super-admin'], true) && isset($data['permissions'])) {
                abort(409, $role->name.' luôn có toàn bộ quyền và không thể chỉnh sửa.');
            }

            if (isset($data['name'])) {
                $role->name = $data['name'];
            }
            if (array_key_exists('description', $data)) {
                $role->description = $data['description'];
            }
            $role->save();

            if (isset($data['permissions']) && is_array($data['permissions']) && $role->code !== 'super-admin') {
                $permissionIds = array_map('intval', $data['permissions']);
                $catalogIds = Permission::query()
                    ->whereIn('code', PermissionCatalog::codes())
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                abort_if(array_diff($permissionIds, $catalogIds) !== [], 422, 'Chỉ được gán quyền có trong danh mục hệ thống.');
                $role->permissions()->sync($permissionIds);
            }

            $after = [
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $role->permissions()->pluck('code')->all(),
                'affected_users_count' => $role->users()->count(),
            ];

            $this->audit->record('role.update', 'role', $role->id, $before, $after);

            return $role;
        });
    }

    public function deleteRole(Role $role): array
    {
        return DB::transaction(function () use ($role): array {
            $role = Role::lockForUpdate()->findOrFail($role->id);
            abort_if(
                in_array($role->code, ['admin', 'super-admin'], true),
                409,
                'Không thể xóa vai trò mặc định của hệ thống.'
            );

            $affectedUsersCount = $role->users()->count();
            $permissionsRevoked = $role->permissions()->pluck('code')->all();

            $role->users()->detach();
            $role->permissions()->detach();

            $roleInfo = [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name,
                'affected_users_count' => $affectedUsersCount,
                'revoked_permissions' => $permissionsRevoked,
            ];

            $role->delete();

            $this->audit->record(
                'role.delete',
                'role',
                $roleInfo['id'],
                $roleInfo,
                ['deleted' => true]
            );

            return $roleInfo;
        });
    }
}
