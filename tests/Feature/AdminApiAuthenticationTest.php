<?php

namespace Tests\Feature;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;

class AdminApiAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_login_and_read_json_dashboard(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $admin = $this->admin();
        $admin->update(['password' => 'ValidPassword123']);

        $this->postJson('/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => 'ValidPassword123'])->assertOk()->assertJsonPath('data.user.id', $admin->id);
        $this->getJson('/api/v1/admin')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/api/v1/admin/questions')->assertOk()->assertJsonStructure(['data']);
    }

    public function test_learner_cannot_login_to_admin_api(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $user = User::factory()->create(['password' => 'ValidPassword123']);

        $this->postJson('/api/v1/admin/auth/login', ['email' => $user->email, 'password' => 'ValidPassword123'])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_guest_cannot_read_admin_api(): void
    {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
    }
}
