<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Access\Response;


class ClientPolicy
{
    /**
     * Who can see the client list.
     * Any authenticated user can list their own clients.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Who can view a specific client.
     * Only the owner can view their client.
     */
    public function view(User $user, Client $client): bool
    {
        return $user->id === $client->user_id;
    }

    /**
     * Any authenticated user can create clients.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only the owner can update a client.
     */
    public function update(User $user, Client $client): Response|bool
    {
        if ($user->id !== $client->user_id) {
            return Response::deny('You do not own this client.');
        }

        return true;
    }

    /**
     * Only the owner can delete a client.
     */
    public function delete(User $user, Client $client): bool
    {
        return $user->id === $client->user_id;
    }

    /**
     * Restore a soft-deleted client.
     */
    public function restore(User $user, Client $client): bool
    {
        return $user->id === $client->user_id;
    }

    /**
     * Permanently delete.
     */
    public function forceDelete(User $user, Client $client): bool
    {
        return $user->id === $client->user_id;
    }
}