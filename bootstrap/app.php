<?php

use App\Http\Middleware\ApiResponse;
use App\Http\Middleware\ConfigureFrontend;
use App\Http\Middleware\SurfaceIntendedUrl;
use App\Http\Middleware\ThrottleActionRequests;
use Dotenv\Dotenv;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use VistiqAdmin\Console\Commands\CreateSuperAdmin;
use VistiqAdmin\Console\Commands\ExpireAttempts;
use VistiqAdmin\Http\Middleware\EnsureAccountActive as EnsureAdminAccountActive;
use VistiqAdmin\Http\Middleware\EnsureAdminAccess;
use VistiqAdmin\Http\Middleware\EnsureAdminGuest;
use VistiqLearner\Http\Middleware\EnsureAccountActive as EnsureLearnerAccountActive;
use VistiqLearner\Http\Middleware\EnsureLearnerGuest;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(using: function (): void {
        require __DIR__.'/../routes/web.php';
    }, commands: __DIR__.'/../routes/console.php')
    ->withCommands([ExpireAttempts::class, CreateSuperAdmin::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request): string => $request->is('admin', 'admin/*', 'api/v1/admin', 'api/v1/admin/*') ? route('admin.login') : route('login'));
        $middleware->alias([
            'throttle' => ThrottleActionRequests::class,
            'active.learner' => EnsureLearnerAccountActive::class,
            'active.admin' => EnsureAdminAccountActive::class,
            'admin' => EnsureAdminAccess::class,
            'api.response' => ApiResponse::class,
            'guest.learner' => EnsureLearnerGuest::class,
            'guest.admin' => EnsureAdminGuest::class,
        ]);
        $middleware->prepend(ConfigureFrontend::class);
        $middleware->web(append: [SurfaceIntendedUrl::class]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, SurfaceIntendedUrl::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'value.password', 'value.api_key', 'value.key', 'value.secret', 'value_json']);
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*') || $request->expectsJson());
    })
    ->create();

if (is_file($app->environmentPath().'/.env.local') && ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'testing') {
    Dotenv::createImmutable($app->environmentPath(), '.env')->safeLoad();
    Dotenv::createMutable($app->environmentPath(), '.env.local')->safeLoad();
}

return $app;
