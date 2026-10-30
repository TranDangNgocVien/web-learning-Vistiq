<?php

namespace VistiqAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminGuest
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
