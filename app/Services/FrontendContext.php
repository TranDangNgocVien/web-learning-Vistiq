<?php

namespace App\Services;

class FrontendContext
{
    public function surfaceForPath(?string $path = null): string
    {
        $requestPath = parse_url(
            $path ?? (string) ($_SERVER['REQUEST_URI'] ?? ''),
            PHP_URL_PATH,
        ) ?: '';

        if (preg_match('#^/(?:admin|api/v1/admin)(?:/|$)#', $requestPath) === 1) {
            return 'admin';
        }

        return $path === null && ($_SERVER['HTTP_X_VISTIQ_SURFACE'] ?? '') === 'admin' ? 'admin' : 'learner';
    }

    public function apiPath(string $path, ?string $surface = null): string
    {
        if (str_starts_with($path, '/api/') || str_starts_with($path, '/storage/')) {
            return $path;
        }

        $path = '/'.ltrim($path, '/');
        $surface ??= $this->surfaceForPath($path);

        if ($surface === 'admin') {
            $authPath = match (true) {
                $path === '/admin/login' => '/admin/auth/login',
                $path === '/admin/logout' => '/admin/auth/logout',
                $path === '/admin/forgot-password' => '/admin/auth/forgot-password',
                $path === '/admin/reset-password' => '/admin/auth/reset-password',
                str_starts_with($path, '/admin/reset-password/') => '/admin/auth'.substr($path, strlen('/admin')),
                default => null,
            };

            return '/api/v1'.($authPath ?? $path);
        }

        return '/api/v1'.$path;
    }
}
