<?php

namespace Tests\Admin\Feature\UsersAndPermissions;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Models\Permission;
use VistiqCore\Models\Role;

class RoleManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    public function test_roles_index_requires_roles_manage_permission(): void
    {
        $this->get('/admin/roles')->assertRedirect('/admin/login');

        $learner = User::factory()->create();
        $this->actingAs($learner, 'admin')->get('/admin/roles')->assertForbidden();

        $admin = $this->admin('super-admin');
        $this->actingAs($admin)->get('/admin/roles')
            ->assertOk()
            ->assertSee('Phân quyền theo vai trò')
            ->assertSee('quyền hệ thống');
    }

    public function test_store_role_successfully(): void
    {
        $admin = $this->admin('super-admin');
        $permission = Permission::where('code', 'content.view')->first();

        $response = $this->actingAs($admin)->post('/admin/roles', [
            'code' => 'custom-editor',
            'name' => 'Biên tập viên tùy biến',
            'description' => 'Mô tả vai trò',
            'permissions' => [$permission->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('roles', [
            'code' => 'custom-editor',
            'name' => 'Biên tập viên tùy biến',
        ]);

        $role = Role::where('code', 'custom-editor')->first();
        $this->assertTrue($role->permissions->contains('id', $permission->id));

        $this->assertTrue(
            DB::table('audit_logs')
                ->where('action', 'role.create')
                ->where('entity_id', $role->id)
                ->exists()
        );
    }

    public function test_store_role_validations(): void
    {
        $admin = $this->admin('super-admin');

        // Missing fields
        $this->actingAs($admin)->post('/admin/roles', [])
            ->assertSessionHasErrors(['code', 'name']);

        // Duplicate code
        $this->actingAs($admin)->post('/admin/roles', [
            'code' => 'admin',
            'name' => 'Vai trò trùng mã',
        ])->assertSessionHasErrors('code');

        // Invalid code format
        $this->actingAs($admin)->post('/admin/roles', [
            'code' => 'INVALID_CODE!',
            'name' => 'Mã không đúng định dạng',
        ])->assertSessionHasErrors('code');
    }

    public function test_store_role_rejects_uncatalogued_permission(): void
    {
        $admin = $this->admin('super-admin');
        $fakePermissionId = DB::table('permissions')->insertGetId([
            'code' => 'malicious.hack',
            'name' => 'Hacker code',
        ]);

        $this->actingAs($admin)->post('/admin/roles', [
            'code' => 'hacked-role',
            'name' => 'Hacked Role',
            'permissions' => [$fakePermissionId],
        ])->assertSessionHasErrors('permissions');
    }

    public function test_update_role_details_and_permissions(): void
    {
        $admin = $this->admin('super-admin');
        $role = Role::create([
            'code' => 'test-lead',
            'name' => 'Trưởng nhóm',
            'description' => 'Cũ',
        ]);

        $perm1 = Permission::where('code', 'content.view')->first();
        $perm2 = Permission::where('code', 'content.edit')->first();

        $response = $this->actingAs($admin)->post('/admin/roles/'.$role->id, [
            'name' => 'Trưởng nhóm nội dung',
            'description' => 'Mới',
            'permissions' => [$perm1->id, $perm2->id],
        ]);

        $response->assertRedirect();
        $role->refresh();
        $this->assertSame('Trưởng nhóm nội dung', $role->name);
        $this->assertSame('Mới', $role->description);
        $this->assertCount(2, $role->permissions);

        $this->assertTrue(
            DB::table('audit_logs')
                ->where('action', 'role.update')
                ->where('entity_id', $role->id)
                ->exists()
        );
    }

    public function test_cannot_update_super_admin_permissions(): void
    {
        $admin = $this->admin('super-admin');
        $superAdminRole = Role::where('code', 'super-admin')->first();

        $this->actingAs($admin)->post('/admin/roles/'.$superAdminRole->id, [
            'permissions' => [],
        ])->assertSessionHasErrors('permissions');
    }

    public function test_delete_role_detaches_users_and_records_audit(): void
    {
        $admin = $this->admin('super-admin');
        $role = Role::create([
            'code' => 'temporary-role',
            'name' => 'Vai trò tạm',
            'description' => 'Sẽ xóa',
        ]);

        $perm = Permission::where('code', 'content.view')->first();
        $role->permissions()->attach($perm);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user1->roles()->attach($role);
        $user2->roles()->attach($role);

        $this->assertSame(2, $role->users()->count());

        $response = $this->actingAs($admin)->delete('/admin/roles/'.$role->id);
        $response->assertRedirect('/admin/roles');
        $response->assertSessionHas('status');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('role_user', ['role_id' => $role->id]);
        $this->assertDatabaseMissing('permission_role', ['role_id' => $role->id]);

        $this->assertTrue(
            DB::table('audit_logs')
                ->where('action', 'role.delete')
                ->where('entity_id', $role->id)
                ->exists()
        );
    }

    public function test_cannot_delete_system_roles(): void
    {
        $admin = $this->admin('super-admin');
        $superAdminRole = Role::where('code', 'super-admin')->first();
        $adminRole = Role::where('code', 'admin')->first();

        $this->actingAs($admin)->delete('/admin/roles/'.$superAdminRole->id)->assertStatus(409);
        $this->actingAs($admin)->delete('/admin/roles/'.$adminRole->id)->assertStatus(409);

        $this->assertDatabaseHas('roles', ['code' => 'super-admin']);
        $this->assertDatabaseHas('roles', ['code' => 'admin']);
    }
}
