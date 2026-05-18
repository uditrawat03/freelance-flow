<?php

namespace App\Livewire;

use App\Services\DashboardService;
use Illuminate\Support\Facades\Cache;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard — FreelanceFlow')]
class Dashboard extends Component
{
    // Selected period for the revenue chart
    public string $period = '12months';  // 12months | 6months | 3months

    public function updatedPeriod(): void
    {
        // Invalidate the revenue chart cache when period changes
        Cache::forget("revenue_chart_{$this->period}_" . auth()->id());
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
        ]);
    }
}