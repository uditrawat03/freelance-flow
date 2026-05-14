<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    /**
     * Who can see the project list.
     * Any authenticated user can list their own projects.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Who can view a specific project.
     * Only the owner can view their project.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    /**
     * Any authenticated user can create projects.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only the owner can update a project.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    /**
     * Only the owner can delete a project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    /**
     * Restore a soft-deleted project.
     */
    public function restore(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    /**
     * Permanently delete.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }
}