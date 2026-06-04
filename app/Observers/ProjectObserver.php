<?php

namespace App\Observers;

use App\Models\Project;
use Illuminate\Support\Facades\Cache;

class ProjectObserver
{
    public function created(Project $project): void
    {
        $this->bustCache($project);
    }

    public function updated(Project $project): void
    {
        $this->bustCache($project);
    }

    public function deleted(Project $project): void
    {
        $this->bustCache($project);
    }

    private function bustCache(Project $project): void
    {
        Cache::tags([
            'projects',
            'dashboard',
            "workspace:{$project->workspace_id}",
        ])->flush();
    }
}