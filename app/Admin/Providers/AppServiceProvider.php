<?php

namespace VistiqAdmin\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use VistiqAdmin\Policies\AdminContentPolicy;
use VistiqAdmin\Policies\AttemptPolicy;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\LearningItem;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Models\User;
use VistiqCore\Support\PermissionCatalog;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(ExamAttempt::class, AttemptPolicy::class);
        foreach (PermissionCatalog::codes() as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }
        foreach ([LearningItem::class, LearningStimulus::class, QuestionBankItem::class, ExamTemplate::class] as $model) {
            Gate::policy($model, AdminContentPolicy::class);
        }
    }
}
