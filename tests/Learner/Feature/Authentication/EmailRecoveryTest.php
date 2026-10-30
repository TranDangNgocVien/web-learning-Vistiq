<?php

namespace Tests\Learner\Feature\Authentication;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Learner\TestCase;
use VistiqCore\Models\User as DomainUser;
use VistiqLearner\Models\User;

class EmailRecoveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_emailed_link_can_be_opened_and_used_to_reset_learner_password(): void
    {
        $user = User::factory()->create();
        Notification::fake();
        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect(route('password.sent'))->assertSessionHas('status');
        $this->get(route('password.sent'))->assertOk()
            ->assertSee('Kiểm tra hộp thư')
            ->assertSee('Đã tiếp nhận yêu cầu đặt lại mật khẩu của bạn.')
            ->assertSee('Spam / Thư rác')
            ->assertSee(route('password.request'), false);
        $notification = Notification::sent(DomainUser::findOrFail($user->id), ResetPassword::class)->sole();
        $url = $notification->toMail(DomainUser::findOrFail($user->id))->actionUrl;
        $this->assertStringNotContainsString('/admin/', $url);
        $this->get($url)->assertOk()->assertSee($notification->token);
        $this->post('/reset-password', [
            'email' => $user->email, 'token' => $notification->token,
            'password' => 'NewLearnerPassword123', 'password_confirmation' => 'NewLearnerPassword123',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewLearnerPassword123', $user->fresh()->password));
    }

    public function test_learner_mail_transport_failure_preserves_email_and_shows_a_retry_message(): void
    {
        Password::shouldReceive('sendResetLink')->once()->andThrow(new TransportException('Synthetic transport failure'));
        $this->post('/forgot-password', ['email' => 'learner@example.test'])
            ->assertSessionHasErrors('email')->assertSessionHasInput('email', 'learner@example.test');
    }

    public function test_unknown_email_and_broker_throttling_use_the_same_confirmation_screen(): void
    {
        foreach ([Password::INVALID_USER, Password::RESET_THROTTLED] as $result) {
            Password::shouldReceive('sendResetLink')->once()->andReturn($result);
            $this->post('/forgot-password', ['email' => 'learner@example.test'])
                ->assertRedirect(route('password.sent'))
                ->assertSessionHas('status', 'Nếu email này có tài khoản Vistiq, hướng dẫn đặt lại mật khẩu sẽ được gửi đến hộp thư của bạn.');
        }
    }

    public function test_reset_email_contains_an_embedded_logo_and_a_plain_text_alternative(): void
    {
        config(['mail.default' => 'array']);
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect(route('password.sent'));

        $mail = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame('Reset your password · Vistiq', $mail->getSubject());
        $this->assertStringContainsString('alt="Vistiq"', $mail->getHtmlBody());
        $this->assertStringContainsString('cid:', $mail->getHtmlBody());
        $this->assertStringContainsString('/reset-password/', $mail->getTextBody());
        $this->assertStringContainsString('60 minutes', $mail->getTextBody());
        $attachments = $mail->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertSame('inline', $attachments[0]->getDisposition());
        $this->assertSame('image', $attachments[0]->getMediaType());
        $this->assertSame('png', $attachments[0]->getMediaSubtype());
        $this->assertStringContainsString('cid:'.$attachments[0]->getContentId(), $mail->getHtmlBody());
        $this->assertSame(file_get_contents(public_path('images/vistiq-logo.png')), $attachments[0]->getBody());

        if ($previewPath = getenv('VISTIQ_RESET_MAIL_PREVIEW')) {
            $html = str_replace('cid:'.$attachments[0]->getContentId(), 'data:image/png;base64,'.base64_encode($attachments[0]->getBody()), $mail->getHtmlBody());
            file_put_contents($previewPath, $html);
        }
    }
}
