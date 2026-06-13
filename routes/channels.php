<?php

use App\Models\Workspace;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('workspace.{workspaceId}', function ($user, int $workspaceId): bool {
    return Workspace::query()
        ->whereKey($workspaceId)
        ->whereHas('users', fn ($query) => $query->whereKey($user->id))
        ->exists();
});

Broadcast::channel('App.Models.User.{id}', function ($user, int $id): bool {
    return (int) $user->id === $id;
});
