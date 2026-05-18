<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, Billable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    // Get the currently active workspace from the session
    public function currentWorkspace(): ?Workspace
    {
        $workspaceId = session('current_workspace_id');

        if ($workspaceId) {
            return $this->workspaces()->find($workspaceId);
        }

        // Default to the first workspace the user belongs to
        return $this->workspaces()->first();
    }

    // Switch the active workspace
    public function switchWorkspace(Workspace $workspace): void
    {
        if ($this->hasWorkspaceAccess($workspace)) {
            session(['current_workspace_id' => $workspace->id]);
        }
    }

    public function hasWorkspaceAccess(Workspace $workspace): bool
    {
        return $this->workspaces()->where('workspace_id', $workspace->id)->exists();
    }
}
