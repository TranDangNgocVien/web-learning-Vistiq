<?php

namespace VistiqLearner\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureLearnerGuest
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($user = $request->user()) {
            return $user->isAdmin()
                ? redirect()->route('admin.dashboard')
                : redirect()->route('home');
        }

        return $next($request);
    }
}
