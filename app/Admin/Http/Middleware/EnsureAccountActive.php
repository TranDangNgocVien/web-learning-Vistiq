<?php

namespace VistiqAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use VistiqCore\Services\Shared\CompactDatabase as DB;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($user = $request->user()) {
            if ($user->status !== 'active' || $request->session()->get('admin_session_version', $user->session_version) !== $user->session_version) {
                Auth::logout();
                $request->session()->forget(['admin_session_version', 'admin_otp_verified', 'admin.intended']);
                $request->session()->regenerate(true);
                $request->session()->regenerateToken();
                if ($request->expectsJson()) {
                    abort(401, 'Phiên đăng nhập đã hết hiệu lực.');
                }

                return redirect()->route('admin.login')->withErrors(['email' => 'Tài khoản bị khóa hoặc phiên đã bị thu hồi.']);
            }
            $request->session()->put('admin_session_version', $user->session_version);
            if (! $user->last_active_at || $user->last_active_at->lt(now()->subMinutes(5))) {
                $user->forceFill(['last_active_at' => now()])->save();
                DB::table('user_activities')->insert(['user_id' => $user->id, 'action' => 'visit', 'entity' => $request->path(), 'occurred_at' => now()]);
            }
        }

        return $next($request);
    }
}
