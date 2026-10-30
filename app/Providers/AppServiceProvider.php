<?php

namespace App\Providers;

use App\Routing\ApiControllerDispatcher;
use App\Services\FrontendContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Routing\Contracts\ControllerDispatcher;
use Illuminate\Support\ServiceProvider;
use VistiqAdmin\Jobs\ProcessAiTask;
use VistiqCore\Models\User;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend('validation.presence', fn ($verifier, $app) => new \VistiqCore\Services\Shared\CompactPresenceVerifier($app['db']));
        $this->app->singleton(ControllerDispatcher::class, ApiControllerDispatcher::class);
        $this->app->scoped('vistiq.surface', function ($app): string {
            $path = $app->bound('request') ? $app->make('request')->getPathInfo() : null;

            return $app->make(FrontendContext::class)->surfaceForPath($path);
        });
        config(['auth.providers.users.model' => User::class]);
        $this->app->register(\VistiqLearner\Providers\AppServiceProvider::class);
        $this->app->register(\VistiqAdmin\Providers\AppServiceProvider::class);
    }

    public function boot(): void
    {
        if (! class_exists('App\\Jobs\\ProcessAiTask', false)) {
            class_alias(ProcessAiTask::class, 'App\\Jobs\\ProcessAiTask');
        }
        Factory::guessFactoryNamesUsing(function (string $model): string {
            $surface = str_starts_with($model, 'VistiqLearner\\') ? 'Learner' : 'Admin';

            return 'Database\\'.$surface.'\\Factories\\'.class_basename($model).'Factory';
        });
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $path = app('vistiq.surface') === 'admin' ? '/admin/reset-password/' : '/reset-password/';

            return rtrim((string) config('app.url'), '/').$path.$token.'?'.http_build_query(['email' => $user->email]);
        });
    }
}
