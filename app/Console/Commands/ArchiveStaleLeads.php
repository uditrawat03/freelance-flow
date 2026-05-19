<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

class ArchiveStaleLeads extends Command
{
    protected $signature = 'clients:archive-leads
                            {--days=90 : Archive leads that have not been updated in this many days}
                            {--dry-run : Preview without archiving}';

    protected $description = 'Move stale lead clients to inactive status.';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $staleLeads = Client::leads()
            ->where('updated_at', '<', now()->subDays($days))
            ->get();

        if ($staleLeads->isEmpty()) {
            $this->info("No stale leads found (inactive for more than {$days} days).");
            return self::SUCCESS;
        }

        $this->info("Found {$staleLeads->count()} stale lead(s).");

        if ($this->option('dry-run')) {
            $this->table(
                ['Name', 'Email', 'Last Updated'],
                $staleLeads->map(fn($c) => [
                    $c->name,
                    $c->email,
                    $c->updated_at->diffForHumans(),
                ])
            );
            return self::SUCCESS;
        }

        $staleLeads->each->update(['status' => 'inactive']);

        $this->info("Archived {$staleLeads->count()} lead(s) to inactive.");

        return self::SUCCESS;
    }
}