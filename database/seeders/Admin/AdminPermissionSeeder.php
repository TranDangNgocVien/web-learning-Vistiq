<?php

namespace Database\Admin\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use VistiqCore\Support\PermissionCatalog;

class AdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = PermissionCatalog::definitions();

        foreach ($definitions as $code => $definition) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                ['name' => $definition['name']],
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('code', array_keys($definitions))
            ->pluck('id', 'code');

        foreach (PermissionCatalog::defaultRoles() as $code => $role) {
            $existingRoleId = DB::table('roles')->where('code', $code)->value('id');
            $now = now();

            if ($existingRoleId) {
                DB::table('roles')->where('id', $existingRoleId)->update([
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'updated_at' => $now,
                ]);
                $roleId = $existingRoleId;
            } else {
                $roleId = DB::table('roles')->insertGetId([
                    'code' => $code,
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $codes = $role['permissions'] === '*'
                ? array_keys($definitions)
                : $role['permissions'];

            foreach ($codes as $permissionCode) {
                $permissionId = $permissionIds->get($permissionCode);

                if ($permissionId !== null) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }
}
