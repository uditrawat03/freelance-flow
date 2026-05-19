<?php

namespace App\Repositories\Eloquent;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentClientRepository implements ClientRepositoryInterface
{
    public function paginate(
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

    public function find(int $id): ?Client
    {
        return Client::find($id);
    }

    public function findOrFail(int $id): Client
    {
        return Client::findOrFail($id);
    }

    public function create(array $data): Client
    {
        return Client::create($data);
    }

    public function update(Client $client, array $data): Client
    {
        $client->update($data);
        return $client->fresh();
    }

    public function delete(Client $client): void
    {
        $client->projects()->each(fn($p) => $p->delete());
        $client->delete();
    }

    public function countByStatus(): array
    {
        return Client::query()
            ->select('status', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function activeClients(): Collection
    {
        return Client::active()->orderBy('name')->get();
    }
}