<?php

namespace VistiqLearner\Http\Controllers\Authentication\Mail;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/** Public mail presentation contract for all password-reset send actions. */
final class ResetPasswordEmail
{
    public static function configure(): void
    {
        ResetPassword::toMailUsing(static function ($user, string $token): MailMessage {
            $resetUrl = ResetPassword::$createUrlCallback
                ? call_user_func(ResetPassword::$createUrlCallback, $user, $token)
                : url(route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false));

            return (new MailMessage)
                ->subject('Reset your password · Vistiq')
                ->action('Reset Password', $resetUrl)
                ->view([
                    'html' => 'pages.learner.authentication.emails.reset-password',
                    'text' => 'pages.learner.authentication.emails.reset-password-text',
                ], [
                    'resetUrl' => $resetUrl,
                    'expiresInMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
                ]);
        });
    }
}
