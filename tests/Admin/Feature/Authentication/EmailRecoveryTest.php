<?php

namespace Tests\Admin\Feature\Authentication;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Admin\TestCase;
use VistiqCore\Models\User;

class EmailRecoveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_email_link_resets_password_and_revokes_existing_sessions(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $admin = $this->admin();
        $version = $admin->session_version;
        Notification::fake();
        $this->post('/admin/forgot-password', ['email' => strtoupper($admin->email)])->assertSessionHas('status');
        $notification = Notification::sent(User::findOrFail($admin->id), ResetPassword::class)->sole();
        $url = $notification->toMail(User::findOrFail($admin->id))->actionUrl;
        $this->assertStringContainsString('/admin/reset-password/', $url);
        DB::table('sessions')->insert(['id' => 'admin-old-session', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => time()]);
        $credentials = ['email' => $admin->email, 'token' => $notification->token, 'password' => 'NewAdminPassword123', 'password_confirmation' => 'NewAdminPassword123'];
        $this->post('/admin/reset-password', $credentials)->assertRedirect(route('admin.login'));
        $this->assertTrue(Hash::check('NewAdminPassword123', $admin->fresh()->password));
        $this->assertSame($version + 1, $admin->fresh()->session_version);
        $this->assertDatabaseMissing('sessions', ['user_id' => $admin->id]);
        $this->post('/admin/reset-password', $credentials)->assertSessionHasErrors('email');
    }

    public function test_admin_mail_transport_failure_is_recoverable_and_keeps_email(): void
    {
        Password::shouldReceive('sendResetLink')->once()->andThrow(new TransportException('Synthetic transport failure'));
        $this->post('/admin/forgot-password', ['email' => 'admin@example.test'])
            ->assertSessionHasErrors('email')->assertSessionHasInput('email', 'admin@example.test');
    }

    public function test_unknown_admin_email_has_the_same_public_confirmation(): void
    {
        $this->post('/admin/forgot-password', ['email' => 'unknown@example.test'])
            ->assertSessionHas('status', 'Nếu tài khoản tồn tại, hướng dẫn khôi phục đã được gửi.');
    }
}
