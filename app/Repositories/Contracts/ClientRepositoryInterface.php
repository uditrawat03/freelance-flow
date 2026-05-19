<?php

namespace App\Repositories\Contracts;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ClientRepositoryInterface
{
    public function paginate(
        string $search = '',
        string $status = '',
        int $perPage = 15,
    ): LengthAwarePaginator;

    public function find(int $id): ?Client;

    public function findOrFail(int $id): Client;

    public function create(array $data): Client;

    public function update(Client $client, array $data): Client;

    public function delete(Client $client): void;

    public function countByStatus(): array;

    public function activeClients(): Collection;
}