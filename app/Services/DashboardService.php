<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
        private readonly ProjectRepositoryInterface $projects,
        private readonly InvoiceRepositoryInterface $invoices,
    ) {
    }

    private function workspaceId(): int|null
    {
        return auth()->user()->currentWorkspace()?->id;
    }

    private function cache(): \Illuminate\Cache\TaggedCache
    {
        return Cache::tags([
            'dashboard',
            "workspace:{$this->workspaceId()}",
        ]);
    }

    public function stats(): array
    {
        $workspaceId = $this->workspaceId();

        return $this->cache()->remember("dashboard_stats_{$workspaceId}", 300, function () {
            return [
                'total_clients' => Client::active()->count(),
                'active_projects' => Project::active()->count(),
                'unpaid_invoices' => Invoice::unpaid()->count(),
                'overdue_invoices' => Invoice::overdue()->count(),
                'total_revenue' => $this->invoices->totalRevenue(),
                'revenue_this_month' => Invoice::paid()
                    ->whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->sum('total'),
            ];
        });
    }

    public function revenueChart(int $months = 12): array
    {
        $workspaceId = $this->workspaceId();

        return $this->cache()->remember("revenue_chart_{$months}_{$workspaceId}", 300, function () use ($months) {
            $chart = $this->invoices->revenueByMonth($months);
            $chart['total'] = array_sum($chart['data']);
            return $chart;
        });
    }

    public function projectStatusBreakdown(): array
    {
        return $this->projects->statusBreakdown();
    }

    public function recentActivity(): array
    {
        return [
            'clients' => Client::latest()->limit(5)->get(),
            'projects' => Project::with('client')->latest()->limit(5)->get(),
            'invoices' => Invoice::with('client')->latest()->limit(5)->get(),
        ];
    }

    public function overdueItems(): array
    {
        return [
            'invoices' => $this->invoices->overdueInvoices()->take(5),
            'projects' => $this->projects->overdueProjects()->take(5),
        ];
    }

    public function bustCache(): void
    {
        Cache::tags([
            'dashboard',
            "workspace:{$this->workspaceId()}",
        ])->flush();

        Cache::forget("recent_activity_{$this->workspaceId()}");
    
        // $workspaceId = $this->workspaceId();

        // foreach (['dashboard_stats', 'revenue_chart_3', 'revenue_chart_6', 'revenue_chart_12'] as $key) {
        //     $this->cache()->forget("{$key}_{$workspaceId}");
        // }
    }
}