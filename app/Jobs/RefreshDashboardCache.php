<?php

namespace App\Jobs;

use App\Models\Workspace;
use App\Services\DashboardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshDashboardCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $workspaceId,
    ) {}

    public function handle(DashboardService $dashboardService): void
    {
        $workspace = Workspace::with('owner')->find($this->workspaceId);

        if (! $workspace || ! $workspace->owner) {
            return;
        }

        // Set auth context for the global scope to work
        auth()->guard('web')->login($workspace->owner);
        session(['current_workspace_id' => $workspace->id]);

        // Bust and rebuild
        $dashboardService->bustCache();
        $dashboardService->stats();
        $dashboardService->revenueChart(12);
        $dashboardService->projectStatusBreakdown();

        auth()->guard('web')->logout();
    }
}