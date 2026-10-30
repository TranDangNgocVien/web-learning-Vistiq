<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SurfaceIntendedUrl
{
    public function handle(Request $request, Closure $next): mixed
    {
        $surface = $request->is('admin', 'admin/*', 'api/v1/admin', 'api/v1/admin/*') ? 'admin' : 'learner';
        if ($surface === 'learner' && auth('web')->user()?->isAdmin()) {
            // Expire legacy Admin identities that were stored in the old learner guard.
            auth('web')->logoutCurrentDevice();
            $request->session()->forget('user_session_version');
            if ($request->is('/')) {
                return redirect()->route(auth('admin')->user()?->isAdmin() ? 'admin.dashboard' : 'admin.login');
            }
        }
        $key = $surface.'.intended';
        $intended = $request->session()->pull($key);
        $legacy = $request->session()->pull('url.intended');
        $candidate = $intended ?? $legacy;
        if (is_string($candidate)) {
            $path = parse_url($candidate, PHP_URL_PATH) ?: '';
            $isAdmin = preg_match('#^/(?:admin|api/v1/admin)(?:/|$)#', $path) === 1;
            if (($surface === 'admin') === $isAdmin) {
                $request->session()->put('url.intended', $candidate);
            }
        }
        try {
            return $next($request);
        } finally {
            if ($request->session()->has('url.intended')) {
                $request->session()->put($key, $request->session()->pull('url.intended'));
            }
        }
    }
}
