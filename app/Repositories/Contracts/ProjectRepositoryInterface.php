<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProjectRepositoryInterface
{
    public function paginate(
        string $search = '',
        string $status = '',
        ?int $clientId = null,
        int $perPage = 15,
    ): LengthAwarePaginator;

    public function find(int $id): ?Project;

    public function findOrFail(int $id): Project;

    public function create(array $data): Project;

    public function update(Project $project, array $data): Project;

    public function delete(Project $project): void;

    public function overdueProjects(): Collection;

    public function statusBreakdown(): array;

    public function forClient(int $clientId): Collection;
}