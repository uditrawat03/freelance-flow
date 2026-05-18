<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ClientService
{
    /**
     * Paginated, filtered client list for the current workspace.
     */
    public function list(
        string $search = '',
        string $status = '',
        int $perPage = 15,
    ): LengthAwarePaginator {
        return Client::query()
            ->withCount('projects')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%");
                });
            })
            ->when($status, fn($q) => $q->status($status))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Create a new client and assign to current workspace.
     */
    public function create(array $data): Client
    {
        return Client::create($data);
        // workspace_id and user_id are auto-assigned via model booted() hooks
    }

    /**
     * Update an existing client.
     */
    public function update(Client $client, array $data): Client
    {
        $client->update($data);
        return $client->fresh();
    }

    /**
     * Soft delete a client and their projects.
     */
    public function delete(Client $client): void
    {
        // Soft-delete all projects before deleting the client
        $client->projects()->each(fn($project) => $project->delete());
        $client->delete();
    }

    /**
     * Summary statistics for the current workspace.
     * Cached for 5 minutes per workspace.
     */
    public function statistics(): array
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;

        return Cache::remember("client_stats_{$workspaceId}", 300, function () {
            return [
                'total' => Client::count(),
                'active' => Client::active()->count(),
                'inactive' => Client::inactive()->count(),
                'leads' => Client::leads()->count(),
            ];
        });
    }

    /**
     * Bust the client statistics cache.
     * Call whenever a client is created, updated, or deleted.
     */
    public function bustCache(): void
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;
        Cache::forget("client_stats_{$workspaceId}");
    }
}