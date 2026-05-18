<?php

namespace App\Services;

use App\Events\ProjectCreated;
use App\Models\Project;
use Illuminate\Support\Collection;

class ProjectService
{
    /**
     * Create a project and fire the ProjectCreated event.
     */
    public function create(array $data, array $tagIds = []): Project
    {
        $project = Project::create($data);

        if (!empty($tagIds)) {
            $project->tags()->sync($tagIds);
        }

        // Event fires listeners: SendProjectNotification, LogProjectActivity
        ProjectCreated::dispatch($project);

        return $project;
    }

    /**
     * Update project and sync tags.
     */
    public function update(Project $project, array $data, array $tagIds = []): Project
    {
        $previousStatus = $project->status;

        $project->update($data);
        $project->tags()->sync($tagIds);

        // Fire status change notification if status changed
        if ($previousStatus !== $project->status) {
            $project->loadMissing('client');
            auth()->user()->notify(
                new \App\Notifications\ProjectStatusChanged($project, $previousStatus)
            );
        }

        return $project->fresh(['client', 'tags']);
    }

    /**
     * Soft delete a project and remove file attachments.
     */
    public function delete(Project $project): void
    {
        // Delete stored files before soft-deleting
        $project->attachments->each(function ($attachment) {
            $attachment->deleteFromStorage();
            $attachment->delete();
        });

        $project->delete();
    }

    /**
     * Projects that are overdue for the current workspace.
     */
    public function overdueProjects(): Collection
    {
        return Project::overdue()
            ->with('client')
            ->latest('deadline')
            ->get();
    }

    /**
     * Status distribution for the current workspace.
     */
    public function statusBreakdown(): array
    {
        return Project::query()
            ->select('status', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }
}