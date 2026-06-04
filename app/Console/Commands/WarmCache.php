<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\DashboardService;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cache:warm {--workspace= : Warm cache for a specific workspace ID}')]
#[Description('Pre-fill the Redis cache with dashboard data for all workspaces.')]
class WarmCache extends Command
{
    protected $signature = 'cache:warm
                            {--workspace= : Warm cache for a specific workspace ID}';

    protected $description = 'Pre-fill the Redis cache with dashboard data for all workspaces.';

    public function handle(DashboardService $dashboardService): int
    {
        $workspaces = Workspace::query()
            ->when($this->option('workspace'), fn ($q) => $q->where('id', $this->option('workspace')))
            ->get();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($workspaces->count());
        $bar->start();

        foreach ($workspaces as $workspace) {
            // Simulate auth context for the workspace owner
            // so the BelongsToWorkspace scope works correctly
            $owner = $workspace->owner;

            if (! $owner) {
                $bar->advance();
                continue;
            }

            auth()->guard('web')->login($owner);
            session(['current_workspace_id' => $workspace->id]);

            try {
                // Warm each data type
                $dashboardService->stats();
                $dashboardService->revenueChart(3);
                $dashboardService->revenueChart(6);
                $dashboardService->revenueChart(12);
                $dashboardService->projectStatusBreakdown();

                $this->line("\n  ✓ Warmed: {$workspace->name}");
            } catch (\Throwable $e) {
                $this->warn("\n  ✗ Failed: {$workspace->name} — {$e->getMessage()}");
            }

            auth()->guard('web')->logout();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Cache warm-up complete.');

        return self::SUCCESS;
    }
}

