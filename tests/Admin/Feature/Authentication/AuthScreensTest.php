<?php

namespace Tests\Admin\Feature\Authentication;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;

class AuthScreensTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_login_and_password_recovery_render_and_registration_is_not_exposed(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('Đăng nhập quản trị');
        $this->get('/admin/forgot-password')->assertOk()->assertSee('Khôi phục mật khẩu');
        $this->get('/admin/register')->assertNotFound();
    }

    public function test_learner_cannot_login_to_admin_and_locked_admin_is_rejected(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $learner = User::factory()->create(['password' => Hash::make('TestPassword123')]);
        $this->post('/admin/login', ['email' => $learner->email, 'password' => 'TestPassword123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $admin = $this->admin();
        $admin->forceFill(['password' => Hash::make('TestPassword123'), 'status' => 'locked'])->save();
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'TestPassword123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_login_stays_on_admin_surface_even_with_a_learner_intended_url(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $admin = $this->admin();
        $admin->update(['password' => 'TestPassword123']);
        $this->withSession(['url.intended' => url('/exams')]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'TestPassword123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_credentials_cannot_login_through_learner_surface(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $admin = $this->admin();
        $admin->update(['password' => 'TestPassword123']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'TestPassword123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_learner_session_can_open_admin_login_and_switch_to_admin(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $learner = User::factory()->create(['password' => 'TestPassword123']);
        $admin = $this->admin();
        $admin->update(['password' => 'TestPassword123']);
        $this->actingAs($learner);

        $this->get('/admin/login')->assertOk();
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'TestPassword123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_password_reset_form_receives_token_and_email(): void
    {
        $this->get('/admin/reset-password/acceptance-token?email=admin%40example.invalid')->assertOk()->assertSee('name="token" value="acceptance-token"', false)->assertSee('admin@example.invalid')->assertSee(route('admin.password.update'), false)->assertSee('Mật khẩu mới');
    }
}
