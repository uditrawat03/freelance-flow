<?php

namespace App\Observers;

use App\Models\Client;
use Illuminate\Support\Facades\Cache;

class ClientObserver
{
    private function workspaceId(Client $client): int|null
    {
        return $client->workspace_id;
    }

    public function created(Client $client): void
    {
        $this->bustCache($client);
    }

    public function updated(Client $client): void
    {
        $this->bustCache($client);
    }

    public function deleted(Client $client): void
    {
        $this->bustCache($client);
    }

    private function bustCache(Client $client): void
    {
        $workspaceId = $this->workspaceId($client);

        Cache::tags([
            'clients',
            'dashboard',
            "workspace:{$workspaceId}",
        ])->flush();
    }
}