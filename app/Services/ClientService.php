<?php

namespace App\Services;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ClientService
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
    ) {
    }

    private function workspaceId(): int|null
    {
        return auth()->user()->currentWorkspace()?->id;
    }

    public function list(
        string $search = '',
        string $status = '',
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->clients->paginate($search, $status, $perPage);
    }

    public function create(array $data): Client
    {
        $client = $this->clients->create($data);
        $this->bustCache();
        return $client;
    }

    public function update(Client $client, array $data): Client
    {
        $updated = $this->clients->update($client, $data);
        $this->bustCache();
        return $updated;
    }

    public function delete(Client $client): void
    {
        $this->clients->delete($client);
        $this->bustCache();
    }

    public function statistics(): array
    {
        $workspaceId = $this->workspaceId();

        return Cache::tags([
            'clients',
            "workspace:{$workspaceId}",
        ])->remember("client_stats_{$workspaceId}", 300, function () {
            return $this->clients->countByStatus();
        });
    }

    public function bustCache(): void
    {
        Cache::tags([
            'clients',
            "workspace:{$this->workspaceId()}",
        ])->flush();

        // Also bust dashboard since client count changes affect it
        Cache::tags([
            'dashboard',
            "workspace:{$this->workspaceId()}",
        ])->flush();

        // $workspaceId = $this->workspaceId();
        // Cache::forget("client_stats_{$workspaceId}");
    }
}