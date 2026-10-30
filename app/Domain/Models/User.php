<?php

namespace VistiqCore\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['status' => 'active', 'session_version' => 0];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime', 'last_active_at' => 'datetime'];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasRole(string $code): bool
    {
        return $this->accessSnapshot()['roles']->contains($code);
    }

    public function hasPermission(string $code): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $access = $this->accessSnapshot();

        return $access['roles']->contains('super-admin') || $access['permissions']->contains($code);
    }

    private function accessSnapshot(): array
    {
        // Only cache the authenticated principal, for this HTTP request. Never cache across requests.
        $request = request();
        $key = 'vistiq.access.'.spl_object_id($this);
        if (! $request->attributes->has($key)) {
            $roles = $this->roles()->with('permissions')->get();
            $request->attributes->set($key, ['roles' => $roles->pluck('code'), 'permissions' => $roles->flatMap(fn ($role) => $role->permissions->pluck('code'))->unique()]);
        }

        return $request->attributes->get($key);
    }

    public function isAdmin(): bool
    {
        return $this->hasPermission('admin.access');
    }
}
