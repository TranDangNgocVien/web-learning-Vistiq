<?php

namespace VistiqAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use VistiqCore\Services\System\SettingsService;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Tài khoản không có quyền quản trị.');
        if (app(SettingsService::class)->get('security')['admin_otp'] && ! $request->session()->get('admin_otp_verified')) {
            if ($request->expectsJson()) {
                abort(403, 'Cần xác thực OTP quản trị.');
            }

            return redirect()->route('admin.otp.show');
        }

        return $next($request);
    }
}
