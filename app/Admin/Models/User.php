<?php

namespace VistiqAdmin\Models;

use Database\Admin\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use VistiqCore\Models\User as DomainUser;

class User extends DomainUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
