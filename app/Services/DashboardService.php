<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function __construct(
        private readonly ClientService $clientService,
        private readonly ProjectService $projectService,
    ) {
    }

    /**
     * All key metrics for the dashboard stats grid.
     */
    public function stats(): array
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;

        return Cache::remember("dashboard_stats_{$workspaceId}", 300, function () {
            return [
                'total_clients' => Client::active()->count(),
                'active_projects' => Project::active()->count(),
                'unpaid_invoices' => Invoice::unpaid()->count(),
                'overdue_invoices' => Invoice::overdue()->count(),
                'total_revenue' => Invoice::paid()->sum('total'),
                'revenue_this_month' => Invoice::paid()
                    ->whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->sum('total'),
            ];
        });
    }

    /**
     * Revenue chart data for the given number of months.
     */
    public function revenueChart(int $months = 12): array
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;

        return Cache::remember("revenue_chart_{$months}_{$workspaceId}", 300, function () use ($months) {
            $labels = [];
            $data = [];

            for ($i = $months - 1; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $labels[] = $date->format('M Y');
                $data[] = (float) Invoice::paid()
                    ->whereMonth('paid_at', $date->month)
                    ->whereYear('paid_at', $date->year)
                    ->sum('total');
            }

            return ['labels' => $labels, 'data' => $data, 'total' => array_sum($data)];
        });
    }

    /**
     * Project status breakdown for the doughnut chart.
     */
    public function projectStatusBreakdown(): array
    {
        return $this->projectService->statusBreakdown();
    }

    /**
     * Recent activity for the dashboard feed.
     */
    public function recentActivity(): array
    {
        return [
            'clients' => Client::latest()->limit(5)->get(),
            'projects' => Project::with('client')->latest()->limit(5)->get(),
            'invoices' => Invoice::with('client')->latest()->limit(5)->get(),
        ];
    }

    /**
     * Overdue items that need attention.
     */
    public function overdueItems(): array
    {
        return [
            'invoices' => Invoice::overdue()->with('client')->limit(5)->get(),
            'projects' => $this->projectService->overdueProjects()->take(5),
        ];
    }

    /**
     * Bust all dashboard caches for the current workspace.
     */
    public function bustCache(): void
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;

        foreach (['dashboard_stats', 'revenue_chart_3', 'revenue_chart_6', 'revenue_chart_12'] as $key) {
            Cache::forget("{$key}_{$workspaceId}");
        }

        $this->clientService->bustCache();
    }
}