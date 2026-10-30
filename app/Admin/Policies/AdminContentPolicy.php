<?php

namespace VistiqAdmin\Policies;

use VistiqCore\Models\User;

class AdminContentPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('content.view');
    }

    public function edit(User $user): bool
    {
        return $user->hasPermission('content.edit');
    }

    public function review(User $user): bool
    {
        return $user->hasPermission('content.review');
    }

    public function publish(User $user): bool
    {
        return $user->hasPermission('content.publish');
    }
}
