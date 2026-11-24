<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')->withPivot('role')->withTimestamps();
    }

    public function roleIn(Workspace $workspace): ?string
    {
        return $this->workspaces()->whereKey($workspace->id)->first()?->pivot->role;
    }

    /** The workspace new projects go into (the first one the user belongs to). */
    public function currentWorkspace(): ?Workspace
    {
        return $this->workspaces()->orderBy('workspaces.id')->first();
    }

    public function belongsToWorkspace(int $workspaceId): bool
    {
        return $this->workspaces()->whereKey($workspaceId)->exists();
    }
}
