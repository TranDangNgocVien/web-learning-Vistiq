<?php

namespace App\Http\Middleware;

use App\Services\FrontendContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ConfigureFrontend
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(FrontendContext::class);
        // Distinct guard keys and remember cookies prevent identities crossing portals.
        $admin = $request->is('admin', 'admin/*', 'api/v1/admin', 'api/v1/admin/*');
        app()->instance('vistiq.surface', $admin ? 'admin' : 'learner');
        Auth::shouldUse($admin ? 'admin' : 'web');
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
        }
        URL::formatPathUsing(fn (string $path, ?Route $route = null): string => $request->is('api/*') && $route !== null ? $context->apiPath($path, app('vistiq.surface')) : $path);

        return $next($request);
    }
}
