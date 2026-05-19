<?php

namespace App\Repositories\Fakes;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class FakeClientRepository implements ClientRepositoryInterface
{
    private Collection $clients;

    public function __construct()
    {
        $this->clients = collect();
    }

    public function paginate(string $search = '', string $status = '', int $perPage = 15): LengthAwarePaginator
    {
        $filtered = $this->clients
            ->when($search, fn($c) => $c->filter(
                fn($client) => str_contains(strtolower($client->name), strtolower($search))
            ))
            ->when($status, fn($c) => $c->where('status', $status))
            ->values();

        return new Paginator($filtered->all(), $filtered->count(), $perPage, 1);
    }

    public function find(int $id): ?Client
    {
        return $this->clients->firstWhere('id', $id);
    }

    public function findOrFail(int $id): Client
    {
        return $this->clients->firstWhere('id', $id)
            ?? throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
    }

    public function create(array $data): Client
    {
        $client = new Client($data);
        $client->id = $this->clients->count() + 1;
        $this->clients->push($client);
        return $client;
    }

    public function update(Client $client, array $data): Client
    {
        $client->fill($data);
        return $client;
    }

    public function delete(Client $client): void
    {
        $this->clients = $this->clients->reject(fn($c) => $c->id === $client->id);
    }

    public function countByStatus(): array
    {
        return $this->clients->groupBy('status')
            ->map->count()
            ->toArray();
    }

    public function activeClients(): Collection
    {
        return $this->clients->where('status', 'active')->values();
    }

    // Helper for tests — seed the fake with data
    public function seed(array $clients): void
    {
        foreach ($clients as $data) {
            $this->clients->push(new Client($data));
        }
    }
}