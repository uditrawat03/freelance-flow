<?php

namespace App\Services;

use App\Events\ProjectCreated;
use App\Events\ProjectStatusUpdated;
use App\Models\Project;
use App\Notifications\ProjectStatusChanged;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Support\Collection;

class ProjectService
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
    ) {
    }

    public function create(array $data, array $tagIds = []): Project
    {
        $project = $this->projects->create($data);

        if (!empty($tagIds)) {
            $project->tags()->sync($tagIds);
        }

        ProjectCreated::dispatch($project);

        return $project;
    }

    public function update(Project $project, array $data, array $tagIds = []): Project
    {
        $previousStatus = $project->status;

        $updated = $this->projects->update($project, $data);
        $updated->tags()->sync($tagIds);

        if ($previousStatus !== $updated->status) {
            $updated->loadMissing('client');
            auth()->user()->notify(
                new ProjectStatusChanged($updated, $previousStatus)
            );

            event(ProjectStatusUpdated::fromProject($updated, $previousStatus));
        }

        return $updated;
    }

    public function delete(Project $project): void
    {
        $this->projects->delete($project);
    }

    public function overdueProjects(): Collection
    {
        return $this->projects->overdueProjects();
    }

    public function statusBreakdown(): array
    {
        return $this->projects->statusBreakdown();
    }
}
