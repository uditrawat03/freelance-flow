<?php

namespace App\Observers;

use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        $this->bustCache();
    }

    public function updated(Invoice $invoice): void
    {
        $this->bustCache();
    }

    public function deleted(Invoice $invoice): void
    {
        $this->bustCache();
    }

    private function bustCache(): void
    {
        // Clear dashboard stats cache for all users
        // In a multi-user system, scope this to auth()->id()
        Cache::forget('dashboard_stats_' . auth()->id());
    }
}