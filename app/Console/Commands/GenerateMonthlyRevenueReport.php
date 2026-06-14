<?php

namespace App\Console\Commands;

use App\Mail\MonthlyRevenueReport;
use App\Models\Invoice;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class GenerateMonthlyRevenueReport extends Command
{
    protected $signature = 'reports:monthly-revenue
                            {--month= : Month to report on (YYYY-MM, defaults to last month)}
                            {--workspace= : Specific workspace ID, defaults to all}';

    protected $description = 'Generate and email monthly revenue reports to workspace owners.';

    public function handle(): int
    {
        $monthInput = $this->option('month');
        $targetDate = $monthInput
            ? Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth()
            : now()->subMonth()->startOfMonth();

        $this->info("Generating revenue report for: {$targetDate->format('F Y')}");

        $workspaceQuery = Workspace::with('owner');

        if ($workspaceId = $this->option('workspace')) {
            $workspaceQuery->where('id', $workspaceId);
        }

        $workspaces = $workspaceQuery->get();

        foreach ($workspaces as $workspace) {
            $report = $this->buildReport($workspace, $targetDate);

            $this->line("  Workspace: {$workspace->name}");
            $this->line('    Revenue:  '.config('freelanceflow.invoice.currency_symbol', 'Rs.').number_format($report['total_revenue'], 2));
            $this->line("    Invoices: {$report['invoices_paid']} paid, {$report['invoices_outstanding']} outstanding");

            // Email the report to the workspace owner
            if ($workspace->owner?->email) {
                Mail::to($workspace->owner->email)
                    ->queue(new MonthlyRevenueReport($workspace, $report, $targetDate));

                $this->line("    OK Report emailed to {$workspace->owner->email}");
            }
        }

        $this->info('Monthly revenue reports generated.');

        return self::SUCCESS;
    }

    private function buildReport(Workspace $workspace, Carbon $date): array
    {
        $paid = Invoice::where('workspace_id', $workspace->id)
            ->where('status', 'paid')
            ->whereMonth('paid_at', $date->month)
            ->whereYear('paid_at', $date->year)
            ->get();

        $outstanding = Invoice::where('workspace_id', $workspace->id)
            ->whereIn('status', ['sent', 'overdue'])
            ->get();

        return [
            'total_revenue' => $paid->sum('total'),
            'invoices_paid' => $paid->count(),
            'invoices_outstanding' => $outstanding->count(),
            'outstanding_amount' => $outstanding->sum('total'),
            'top_clients' => $paid->groupBy('client_id')
                ->map(fn ($inv) => $inv->sum('total'))
                ->sortDesc()
                ->take(5)
                ->toArray(),
        ];
    }
}
