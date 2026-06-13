<?php

namespace App\Livewire;

use App\Services\DashboardService;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard - FreelanceFlow')]
class Dashboard extends Component
{
    public string $period = '12months';

    public function updatedPeriod(): void
    {
        Cache::forget("revenue_chart_{$this->period}_" . auth()->id());
    }

    #[On('echo-private:workspace.{workspaceId},.project.status.updated')]
    public function handleProjectStatusUpdated(array $event): void
    {
        app(DashboardService::class)->bustCacheForWorkspace((int) $event['workspace_id']);

        $this->dispatch(
            'notify',
            message: sprintf(
                'Project "%s" status changed to %s.',
                $event['project_name'],
                $event['status_label'],
            ),
            type: 'info',
        );
    }

    #[On('echo-private:workspace.{workspaceId},.dashboard.metrics.invalidated')]
    public function handleDashboardMetricsInvalidated(array $event): void
    {
        app(DashboardService::class)->bustCacheForWorkspace((int) $event['workspace_id']);
    }

    public function getWorkspaceIdProperty(): int|null
    {
        return auth()->user()->currentWorkspace()?->id;
    }

    public function render(DashboardService $dashboardService)
    {
        $months = match ($this->period) {
            '3months' => 3,
            '6months' => 6,
            default => 12,
        };

        return view('livewire.dashboard', [
            'stats' => $dashboardService->stats(),
            'revenueChart' => $dashboardService->revenueChart($months),
            'projectStatusData' => $dashboardService->projectStatusBreakdown(),
            'recent' => $dashboardService->recentActivity(),
            'overdue' => $dashboardService->overdueItems(),
            'workspaceId' => $this->workspaceId,
        ]);
    }
}
