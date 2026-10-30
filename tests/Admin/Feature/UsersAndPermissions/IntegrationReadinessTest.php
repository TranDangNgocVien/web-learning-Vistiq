<?php

namespace Tests\Admin\Feature\UsersAndPermissions;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Admin\TestCase;
use VistiqCore\Models\Role;

class IntegrationReadinessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    public function test_role_fields_respect_database_column_lengths(): void
    {
        $this->actingAs($this->admin())->postJson('/admin/roles', [
            'code' => str_repeat('a', 41), 'name' => str_repeat('b', 101), 'description' => str_repeat('c', 256),
        ])->assertUnprocessable()->assertJsonValidationErrors(['code', 'name', 'description']);

        $role = Role::create(['code' => 'length-test', 'name' => 'Length test']);
        $this->postJson('/admin/roles/'.$role->id, [
            'name' => str_repeat('b', 101), 'description' => str_repeat('c', 256),
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'description']);
        $this->assertSame('Length test', $role->fresh()->name);
    }

    public function test_default_admin_keeps_all_permissions(): void
    {
        $role = Role::where('code', 'admin')->firstOrFail();
        $count = $role->permissions()->count();
        $this->actingAs($this->admin())->postJson('/admin/roles/'.$role->id, ['permissions' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('permissions');
        $this->assertSame($count, $role->permissions()->count());
    }

    public function test_operator_cannot_lock_their_own_account_even_with_another_super_admin(): void
    {
        $operator = $this->admin();
        $this->admin();
        $this->actingAs($operator)->post('/admin/users/'.$operator->id.'/lock')->assertStatus(409);
        $this->assertSame('active', $operator->fresh()->status);
    }
}
