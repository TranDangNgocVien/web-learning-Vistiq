<?php

namespace Tests\Admin\Feature\UsersAndPermissions;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Models\Role;

class UserManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    public function test_user_list_requires_users_manage_permission(): void
    {
        $this->get('/admin/users')->assertRedirect('/admin/login');

        $learner = User::factory()->create();
        $this->actingAs($learner, 'admin')->get('/admin/users')->assertForbidden();

        $admin = $this->admin('super-admin');
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Quản lý người dùng');
    }

    public function test_user_list_filtering_by_keyword_status_and_role(): void
    {
        $admin = $this->admin('super-admin');
        $user1 = User::factory()->create(['name' => 'Nguyen Van An', 'email' => 'an@example.com', 'status' => 'active']);
        $user2 = User::factory()->create(['name' => 'Tran Thi Binh', 'email' => 'binh@example.com', 'status' => 'locked']);

        $roleContentAdmin = Role::where('code', 'content-admin')->first();
        $user1->roles()->attach($roleContentAdmin);

        $response = $this->actingAs($admin)->get('/admin/users?q=Nguyen');
        $response->assertOk()->assertSee('Nguyen Van An')->assertDontSee('Tran Thi Binh');

        $responseLocked = $this->actingAs($admin)->get('/admin/users?status=locked');
        $responseLocked->assertOk()->assertSee('Tran Thi Binh')->assertDontSee('Nguyen Van An');

        $responseRole = $this->actingAs($admin)->get('/admin/users?role_id='.$roleContentAdmin->id);
        $responseRole->assertOk()->assertSee('Nguyen Van An')->assertDontSee('Tran Thi Binh');
    }

    public function test_user_detail_view(): void
    {
        $admin = $this->admin('super-admin');
        $targetUser = User::factory()->create(['name' => 'Le Van Cuong', 'email' => 'cuong@example.com']);

        $this->actingAs($admin)
            ->get('/admin/users/'.$targetUser->id)
            ->assertOk()
            ->assertSee('Le Van Cuong')
            ->assertSee('cuong@example.com')
            ->assertSee('Thao tác tài khoản');
    }

    public function test_created_date_filter_includes_the_entire_selected_day(): void
    {
        $admin = $this->admin('super-admin');
        User::factory()->create(['name' => 'Boundary Before', 'created_at' => '2026-10-01 23:59:59']);
        User::factory()->create(['name' => 'Boundary Start', 'created_at' => '2026-10-02 00:00:00']);
        User::factory()->create(['name' => 'Boundary End', 'created_at' => '2026-10-02 23:59:59']);
        User::factory()->create(['name' => 'Boundary After', 'created_at' => '2026-10-03 00:00:00']);

        $this->actingAs($admin)->get('/admin/users?q=Boundary&created_from=2026-10-02&created_to=2026-10-02')
            ->assertOk()->assertSee('Boundary Start')->assertSee('Boundary End')
            ->assertDontSee('Boundary Before')->assertDontSee('Boundary After');
    }

    public function test_lock_and_unlock_user_actions(): void
    {
        $admin = $this->admin('super-admin');
        $targetUser = User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->post('/admin/users/'.$targetUser->id.'/lock')
            ->assertSessionHas('status', 'Đã cập nhật tài khoản.');

        $this->assertSame('locked', $targetUser->fresh()->status);

        $this->actingAs($admin)
            ->post('/admin/users/'.$targetUser->id.'/unlock')
            ->assertSessionHas('status', 'Đã cập nhật tài khoản.');

        $this->assertSame('active', $targetUser->fresh()->status);
    }

    public function test_cannot_lock_or_demote_the_last_active_super_admin(): void
    {
        $superAdmin = $this->admin('super-admin');

        $this->actingAs($superAdmin)
            ->post('/admin/users/'.$superAdmin->id.'/lock')
            ->assertStatus(409);

        $this->assertSame('active', $superAdmin->fresh()->status);

        $this->actingAs($superAdmin)
            ->post('/admin/users/'.$superAdmin->id.'/roles', ['roles' => []])
            ->assertStatus(409);

        $this->assertTrue($superAdmin->fresh()->hasRole('super-admin'));
    }

    public function test_assign_roles_to_user_requires_roles_manage_permission(): void
    {
        $reviewer = $this->admin('reviewer'); // has results.view, etc. but not roles.manage
        $targetUser = User::factory()->create();
        $contentRole = Role::where('code', 'content-admin')->first();

        $this->actingAs($reviewer)
            ->post('/admin/users/'.$targetUser->id.'/roles', ['roles' => [$contentRole->id]])
            ->assertForbidden();

        $superAdmin = $this->admin('super-admin');
        $this->actingAs($superAdmin)
            ->post('/admin/users/'.$targetUser->id.'/roles', ['roles' => [$contentRole->id]])
            ->assertSessionHas('status', 'Đã cập nhật tài khoản.');

        $this->assertTrue($targetUser->fresh()->hasRole('content-admin'));
    }

    public function test_audit_log_recorded_on_user_actions(): void
    {
        $admin = $this->admin('super-admin');
        $targetUser = User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)->post('/admin/users/'.$targetUser->id.'/lock');

        $this->assertTrue(
            DB::table('audit_logs')
                ->where('action', 'user.lock')
                ->where('entity_id', $targetUser->id)
                ->exists()
        );
    }
}
