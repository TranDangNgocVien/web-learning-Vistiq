<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reset your password · Vistiq</title></head>
<body style="margin:0;padding:0;background:#f5f7fa;color:#18212f;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#f5f7fa;">
        <tr><td align="center" style="padding:28px 16px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;">
                <tr><td align="center" style="padding:0 0 24px;">
                    <img src="{{ $message->embed(public_path('images/vistiq-logo.png')) }}" width="88" height="88" alt="Vistiq" style="display:block;width:88px;height:88px;border:0;border-radius:12px;">
                </td></tr>
                <tr><td style="padding:32px 24px;background:#ffffff;border:1px solid #e5e9ef;border-radius:12px;">
                    <h1 style="margin:0 0 20px;font-size:22px;line-height:1.4;color:#18212f;">Hello!</h1>
                    <p style="margin:0 0 24px;font-size:16px;line-height:1.7;">You are receiving this email because we received a password reset request for your account.</p>
                    <table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:0 auto 28px;"><tr><td bgcolor="#18181b" style="border-radius:6px;"><a href="{{ $resetUrl }}" style="display:inline-block;padding:14px 24px;border-radius:6px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;">Reset Password</a></td></tr></table>
                    <p style="margin:0 0 16px;font-size:16px;line-height:1.7;">This password reset link will expire in {{ $expiresInMinutes }} minutes.</p>
                    <p style="margin:0 0 20px;font-size:16px;line-height:1.7;">If you did not request a password reset, no further action is required.</p>
                    <p style="margin:0 0 24px;font-size:16px;line-height:1.7;">Regards,<br>Vistiq</p>
                    <div style="padding-top:20px;border-top:1px solid #e5e9ef;">
                        <p style="margin:0 0 8px;font-size:12px;line-height:1.7;color:#64748b;">If the button doesn't work, copy and paste this link into your browser:</p>
                        <a href="{{ $resetUrl }}" style="font-size:12px;line-height:1.7;color:#245dd8;word-break:break-all;overflow-wrap:anywhere;">{{ $resetUrl }}</a>
                    </div>
                </td></tr>
                <tr><td align="center" style="padding:20px 16px;color:#64748b;font-size:12px;">&copy; {{ date('Y') }} Vistiq. All rights reserved.</td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
