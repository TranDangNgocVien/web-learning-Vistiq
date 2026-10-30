<?php

namespace VistiqLearner\Policies;

use VistiqCore\Models\ExamAttempt;
use VistiqCore\Models\User;

class AttemptPolicy
{
    public function view(User $user, ExamAttempt $attempt): bool
    {
        return $user->status === 'active' && $attempt->user_id === $user->id;
    }

    public function answer(User $user, ExamAttempt $attempt): bool
    {
        return $user->status === 'active' && $attempt->user_id === $user->id && ! $attempt->submitted_at;
    }
}
