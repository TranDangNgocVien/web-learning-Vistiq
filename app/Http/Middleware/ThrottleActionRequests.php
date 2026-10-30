<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

class ThrottleActionRequests extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        $action = preg_replace('/^api\./', '', $route?->getName() ?? ($request->method().':'.$route?->uri()));
        // Autosave/event traffic shares its 120/min budget, without consuming start/restore/login limits.
        if (preg_match('/^attempts\.(answers|events|pause|resume|section|navigate|submit|recording|audio)$/', $action)) {
            $action = 'attempts.write';
        }

        return hash('sha256', $action).':'.parent::resolveRequestSignature($request);
    }
}
