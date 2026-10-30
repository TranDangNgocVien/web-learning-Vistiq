<?php

namespace Tests\Admin\Feature\UsersAndPermissions;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Models\Permission;
use VistiqCore\Models\Role;

class StabilityAuditTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    public function test_user_management_permission_does_not_allow_role_mutations(): void
    {
        $operatorRole = Role::create(['code' => 'user-operator', 'name' => 'User Operator']);
        $operatorRole->permissions()->sync(Permission::whereIn('code', ['admin.access', 'users.manage'])->pluck('id'));
        $operator = User::factory()->create();
        $operator->roles()->attach($operatorRole);
        $target = User::factory()->create();
        $targetRole = Role::create(['code' => 'protected-target', 'name' => 'Protected Target']);
        $target->roles()->attach($targetRole);

        $this->actingAs($operator)->get('/admin/users')->assertOk();
        $this->get('/admin/roles')->assertForbidden();
        $this->post('/admin/roles', ['code' => 'unauthorized-role', 'name' => 'Unauthorized'])->assertForbidden();
        $this->post('/admin/roles/'.$targetRole->id, ['name' => 'Unauthorized change', 'permissions' => []])->assertForbidden();
        $this->delete('/admin/roles/'.$targetRole->id)->assertForbidden();
        $this->post('/admin/users/'.$target->id.'/roles', ['roles' => []])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['code' => 'unauthorized-role']);
        $this->assertSame('Protected Target', $targetRole->fresh()->name);
        $this->assertDatabaseHas('role_user', ['user_id' => $target->id, 'role_id' => $targetRole->id]);
        $this->assertSame(0, $target->fresh()->session_version);
    }

    public function test_content_admin_cannot_view_or_change_accounts_by_direct_url(): void
    {
        $target = User::factory()->create(['status' => 'active']);
        $this->actingAs($this->admin('content-admin'))->get('/admin/users/'.$target->id)->assertForbidden();
        foreach (['lock', 'unlock', 'revoke-sessions'] as $action) {
            $this->post('/admin/users/'.$target->id.'/'.$action)->assertForbidden();
        }
        $this->assertSame('active', $target->fresh()->status);
        $this->assertSame(0, $target->fresh()->session_version);
        $this->assertDatabaseMissing('audit_logs', ['entity_type' => 'user', 'entity_id' => $target->id]);
    }

    public function test_saving_the_browser_form_with_all_permissions_unchecked_revokes_them(): void
    {
        $role = Role::create(['code' => 'audit-editor', 'name' => 'Audit Editor']);
        $role->permissions()->attach(Permission::where('code', 'content.view')->firstOrFail());

        // Browsers omit unchecked checkboxes entirely from a form submission.
        $this->actingAs($this->admin())->post('/admin/roles/'.$role->id, [
            'name' => $role->name,
            'description' => 'All checkboxes unchecked',
            'permission_matrix' => '1',
        ])->assertRedirect();

        $this->assertSame(0, $role->permissions()->count());
    }

    public function test_malformed_permissions_return_validation_errors_instead_of_a_server_error(): void
    {
        $role = Role::create(['code' => 'audit-malformed', 'name' => 'Audit Malformed']);

        $this->actingAs($this->admin())->postJson('/admin/roles/'.$role->id, [
            'permissions' => 'invalid-array',
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions');
    }

    public function test_revoke_sessions_rotates_credentials_deletes_sessions_and_audits(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-token']);
        $version = $user->session_version;
        DB::table('sessions')->insert([
            'id' => 'audit-existing-session', 'user_id' => $user->id,
            'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($this->admin())->post('/admin/users/'.$user->id.'/revoke-sessions')
            ->assertRedirect();

        $this->assertSame($version + 1, $user->fresh()->session_version);
        $this->assertNotSame('old-token', $user->fresh()->getRememberToken());
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.revoke-sessions', 'entity_id' => $user->id]);
    }

    public function test_deleting_a_role_preserves_access_granted_by_another_role(): void
    {
        $permission = Permission::where('code', 'content.view')->firstOrFail();
        $removed = Role::create(['code' => 'audit-removed', 'name' => 'Audit Removed']);
        $retained = Role::create(['code' => 'audit-retained', 'name' => 'Audit Retained']);
        $removed->permissions()->attach($permission);
        $retained->permissions()->attach($permission);
        $user = User::factory()->create();
        $user->roles()->attach([$removed->id, $retained->id]);

        $this->actingAs($this->admin())->delete('/admin/roles/'.$removed->id)->assertRedirect();

        $this->assertTrue($user->fresh()->hasPermission('content.view'));
        $this->assertDatabaseHas('role_user', ['user_id' => $user->id, 'role_id' => $retained->id]);
    }
}
