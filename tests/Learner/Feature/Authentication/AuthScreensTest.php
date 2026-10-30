<?php

namespace Tests\Learner\Feature\Authentication;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Learner\TestCase;

class AuthScreensTest extends TestCase
{
    public function test_login_retains_email_and_remember_choice_after_a_failed_submission(): void
    {
        $this->withSession([
            '_old_input' => ['email' => 'learner@example.com', 'remember' => '1'],
            'errors' => (new ViewErrorBag)->put('default', new MessageBag([
                'email' => 'Thông tin đăng nhập chưa chính xác.',
            ])),
        ]);
        $this->app['session']->save();

        $response = $this->get('/login');

        $response->assertSessionHasErrors(['email']);

        $response->assertSee('value="learner@example.com"', false)
            ->assertSee('value="1" checked', false)
            ->assertSee('Thông tin đăng nhập chưa chính xác.')
            ->assertSee('aria-describedby="email-error"', false);
    }

    public function test_registration_displays_password_errors_without_repopulating_passwords(): void
    {
        $this->withSession([
            '_old_input' => ['name' => 'Học viên', 'email' => 'learner@example.com', 'password' => 'Secret12345'],
            'errors' => (new ViewErrorBag)->put('default', new MessageBag([
                'password' => 'Mật khẩu cần ít nhất 8 ký tự.',
                'password_confirmation' => 'Mật khẩu nhập lại chưa khớp.',
            ])),
        ]);
        $this->app['session']->save();

        $response = $this->get('/register');

        $response->assertSessionHasErrors(['password', 'password_confirmation']);

        $response->assertSee('Học viên')
            ->assertSee('Mật khẩu cần ít nhất 8 ký tự.')
            ->assertSee('Mật khẩu nhập lại chưa khớp.')
            ->assertSee('aria-describedby="spotlight-password-help password-error"', false)
            ->assertDontSee('Secret12345');
    }

    public function test_password_recovery_keeps_its_existing_shell(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertSee('data-auth-light-scene', false)
            ->assertDontSee('data-auth-spotlight', false);
    }
}
