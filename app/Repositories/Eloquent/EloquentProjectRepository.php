<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentProjectRepository implements ProjectRepositoryInterface
{
    public function paginate(
        string $search = '',
        string $status = '',
        ?int $clientId = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return Project::query()
            ->with(['client', 'tags'])
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($status, fn($q) => $q->status($status))
            ->when($clientId, fn($q) => $q->where('client_id', $clientId))
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id): ?Project
    {
        return Project::find($id);
    }

    public function findOrFail(int $id): Project
    {
        return Project::findOrFail($id);
    }

    public function create(array $data): Project
    {
        return Project::create($data);
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($data);
        return $project->fresh(['client', 'tags']);
    }

    public function delete(Project $project): void
    {
        // Remove stored attachments before soft-deleting
        $project->attachments->each(function ($attachment) {
            $attachment->deleteFromStorage();
            $attachment->delete();
        });

        $project->delete();
    }

    public function overdueProjects(): Collection
    {
        return Project::overdue()
            ->with('client')
            ->latest('deadline')
            ->get();
    }

    public function statusBreakdown(): array
    {
        return Project::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function forClient(int $clientId): Collection
    {
        return Project::where('client_id', $clientId)
            ->with('tags')
            ->latest()
            ->get();
    }
}