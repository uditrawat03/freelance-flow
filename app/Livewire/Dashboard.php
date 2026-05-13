<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    private function getStats(): array
    {
        $userId = auth()->id();

        return Cache::remember("dashboard_stats_{$userId}", 300, function () {
            return [
                'total_clients'      => Client::active()->count(),
                'active_projects'    => Project::active()->count(),
                'unpaid_invoices'    => Invoice::unpaid()->count(),
                'overdue_invoices'   => Invoice::overdue()->count(),
                'total_revenue'      => Invoice::paid()->sum('total'),
                'revenue_this_month' => Invoice::paid()
                    ->whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->sum('total'),
            ];
        });
    }

    private function getRevenueChartData(): array
    {
        $userId = auth()->id();
        $period = $this->period;

        return Cache::remember("revenue_chart_{$period}_{$userId}", 300, function () use ($period) {
            $months = match($period) {
                '3months'  => 3,
                '6months'  => 6,
                default    => 12,
            };

            // Build labels and data for the last N months
            $data   = [];
            $labels = [];

            for ($i = $months - 1; $i >= 0; $i--) {
                $date = now()->subMonths($i);

                $labels[] = $date->format('M Y');

                $data[] = (float) Invoice::paid()
                    ->whereMonth('paid_at', $date->month)
                    ->whereYear('paid_at', $date->year)
                    ->sum('total');
            }

            return [
                'labels' => $labels,
                'data'   => $data,
                'total'  => array_sum($data),
            ];
        });
    }

    private function getProjectStatusData(): array
    {
        return Project::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    private function getRecentActivity(): array
    {
        return [
            'clients'  => Client::latest()->limit(5)->get(),
            'projects' => Project::with('client')->latest()->limit(5)->get(),
            'invoices' => Invoice::with('client')->latest()->limit(5)->get(),
        ];
    }

    private function getOverdueItems(): array
    {
        return [
            'invoices' => Invoice::overdue()
                ->with('client')
                ->latest('due_at')
                ->limit(5)
                ->get(),
            'projects' => Project::overdue()
                ->with('client')
                ->latest('deadline')
                ->limit(5)
                ->get(),
        ];
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'stats'             => $this->getStats(),
            'revenueChart'      => $this->getRevenueChartData(),
            'projectStatusData' => $this->getProjectStatusData(),
            'recent'            => $this->getRecentActivity(),
            'overdue'           => $this->getOverdueItems(),
        ]);
    }
}