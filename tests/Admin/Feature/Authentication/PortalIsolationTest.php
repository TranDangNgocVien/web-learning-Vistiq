<?php

namespace Tests\Admin\Feature\Authentication;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;

class PortalIsolationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_two_principals_keep_their_profiles_permissions_and_logout_independent(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $admin = $this->admin();
        $admin->update(['password' => 'PortalTest123']);
        $learner = User::factory()->create(['password' => 'PortalTest123', 'name' => 'Separate learner']);
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'PortalTest123'])->assertRedirect('/admin');
        $this->get('/')->assertRedirect('/admin');
        $this->get('/profile')->assertRedirect('/login');
        $this->get('/login')->assertOk();
        $this->post('/login', ['email' => $learner->email, 'password' => 'PortalTest123'])->assertRedirect('/profile');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($learner, 'web');
        $this->get('/profile')->assertOk()->assertSee('Separate learner')->assertDontSee($admin->email);
        $this->get('/admin/users')->assertOk();
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest('admin');
        $this->get('/profile')->assertOk()->assertSee('Separate learner');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'PortalTest123']);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest('web');
        $this->get('/admin')->assertOk();
    }

    public function test_one_portal_revocation_does_not_revoke_the_other_principal(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $admin = $this->admin();
        $learner = User::factory()->create();
        $this->actingAs($admin, 'admin')->actingAs($learner, 'web');
        $this->withSession(['admin_session_version' => -1, 'user_session_version' => $learner->session_version]);
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($learner, 'web');
    }

    public function test_legacy_admin_identity_is_removed_from_the_learner_guard(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $admin = $this->admin();
        $this->actingAs($admin, 'web')->get('/profile')->assertRedirect('/login');
        $this->assertGuest('web');
        $this->get('/login')->assertOk();
    }
}
