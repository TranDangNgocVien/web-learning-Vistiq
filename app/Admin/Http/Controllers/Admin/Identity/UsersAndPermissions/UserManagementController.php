<?php

namespace VistiqAdmin\Http\Controllers\Admin\Identity\UsersAndPermissions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Gate;
use VistiqAdmin\Http\Controllers\Admin\AdminPageController;
use VistiqAdmin\Http\Requests\Admin\Identity\UsersAndPermissions\RoleStoreRequest;
use VistiqAdmin\Http\Requests\Admin\Identity\UsersAndPermissions\RoleUpdateRequest;
use VistiqAdmin\Http\Requests\Admin\Identity\UsersAndPermissions\UserActionRequest;
use VistiqAdmin\Http\Requests\Admin\Identity\UsersAndPermissions\UserIndexRequest;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Models\Permission;
use VistiqCore\Models\Role;
use VistiqCore\Models\User;
use VistiqCore\Services\Identity\UsersAndPermissions\UserManagementService;
use VistiqCore\Support\PermissionCatalog;

class UserManagementController extends AdminPageController
{
    public function index(UserIndexRequest $request): mixed
    {
        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status');
        $roleId = $request->input('role_id');
        $createdFrom = $request->input('created_from');
        $createdTo = $request->input('created_to');

        $usersQuery = User::with('roles')
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($subQuery) use ($q): void {
                    $subQuery->where('email', 'like', '%'.$q.'%')
                        ->orWhere('name', 'like', '%'.$q.'%');
                });
            })
            ->when($status && $status !== 'all', function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($roleId, function ($query) use ($roleId): void {
                $query->whereHas('roles', function ($subQuery) use ($roleId): void {
                    $subQuery->where('roles.id', $roleId);
                });
            })
            ->when($createdFrom, function ($query) use ($createdFrom): void {
                $query->whereDate('created_at', '>=', $createdFrom);
            })
            ->when($createdTo, function ($query) use ($createdTo): void {
                $query->whereDate('created_at', '<=', $createdTo);
            });

        $users = $usersQuery->latest()->orderByDesc('id')->paginate(20)->withQueryString();
        $roles = Role::orderBy('name')->get();

        return $this->page('Người dùng', 'pages.admin.identity.users-and-permissions.workspace.users', [
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'q' => $q,
                'status' => $status ?? 'all',
                'role_id' => $roleId,
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
            ],
        ]);
    }

    public function show(User $user): mixed
    {
        $user->load('roles');
        $reports = DB::table('attempt_reports')
            ->join('exam_attempts', 'exam_attempts.id', '=', 'attempt_reports.exam_attempt_id')
            ->join(DB::source('attempt_report_reasons'), 'attempt_report_reasons.id', '=', 'attempt_reports.attempt_report_reason_id')
            ->where(function ($query) use ($user): void {
                $query->where('exam_attempts.user_id', $user->id)
                    ->orWhere('attempt_reports.reported_by', $user->id);
            })
            ->select('attempt_reports.*', 'attempt_report_reasons.name as reason')
            ->latest('attempt_reports.created_at')
            ->limit(50)
            ->get();

        return $this->page('Hồ sơ '.$user->name, 'pages.admin.identity.users-and-permissions.workspace.user', [
            'user' => $user,
            'reports' => $reports,
            'roles' => Role::orderBy('name')->get(),
            'attempts' => ExamAttempt::with('exam')->where('user_id', $user->id)->latest()->limit(30)->get(),
            'saved' => DB::table('user_saved_items')->join('learning_items', 'learning_items.id', '=', 'user_saved_items.learning_item_id')->where('user_id', $user->id)->limit(50)->get(),
            'activities' => DB::table('user_activities')->where('user_id', $user->id)->latest('occurred_at')->limit(50)->get(),
            'sessions' => DB::table('sessions')->where('user_id', $user->id)->get(),
        ]);
    }

    public function action(UserActionRequest $request, User $user, string $action, UserManagementService $service): mixed
    {
        $service->action($user, $action, $request->validated());

        return back()->with('status', $action === 'password-reset' ? 'Đã gửi email đặt lại mật khẩu.' : 'Đã cập nhật tài khoản.');
    }

    public function roles(Request $request): mixed
    {
        Gate::authorize('roles.manage');

        $permissionsByCode = Permission::query()
            ->whereIn('code', PermissionCatalog::codes())
            ->get()
            ->keyBy('code');

        $permissionGroups = [];
        foreach (PermissionCatalog::groups() as $code => $group) {
            $items = [];
            foreach ($group['permissions'] as $permissionCode => $definition) {
                $permission = $permissionsByCode->get($permissionCode);

                if ($permission) {
                    $items[] = [
                        'id' => $permission->id,
                        'code' => $permissionCode,
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                    ];
                }
            }

            if ($items !== []) {
                $permissionGroups[] = [
                    'code' => $code,
                    'label' => $group['label'],
                    'description' => $group['description'],
                    'permissions' => $items,
                ];
            }
        }

        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderByRaw("CASE code WHEN 'admin' THEN 0 WHEN 'super-admin' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        $selectedId = $request->query('selected');
        $selectedRole = $selectedId
            ? $roles->firstWhere('id', (int) $selectedId)
            : $roles->first();

        if (! $selectedRole && $roles->isNotEmpty()) {
            $selectedRole = $roles->first();
        }

        return $this->page('Phân quyền theo vai trò', 'pages.admin.identity.users-and-permissions.workspace.roles', [
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'permissionGroups' => $permissionGroups,
        ]);
    }

    public function storeRole(RoleStoreRequest $request, UserManagementService $service): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $role = $service->createRole($request->validated());

        return redirect()
            ->route('admin.roles.index', ['selected' => $role->id])
            ->with('status', 'Đã tạo vai trò mới thành công.');
    }

    public function updateRole(RoleUpdateRequest $request, Role $role, UserManagementService $service): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $service->updateRole($role, $request->validated());

        return redirect()
            ->route('admin.roles.index', ['selected' => $role->id])
            ->with('status', 'Đã cập nhật quyền vai trò.');
    }

    public function destroyRole(Request $request, Role $role, UserManagementService $service): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $result = $service->deleteRole($role);

        return redirect()
            ->route('admin.roles.index')
            ->with('status', "Đã xóa vai trò {$result['name']} và gỡ khỏi {$result['affected_users_count']} người dùng.");
    }
}
