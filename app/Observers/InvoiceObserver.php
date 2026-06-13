<?php

namespace App\Observers;

use App\Events\DashboardMetricsInvalidated;
use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        $this->bustCache($invoice);
    }

    public function updated(Invoice $invoice): void
    {
        $this->bustCache($invoice);

        if ($invoice->wasChanged('status') || $invoice->wasChanged('total') || $invoice->wasChanged('paid_at')) {
            DashboardMetricsInvalidated::dispatch(
                (int) $invoice->workspace_id,
                'invoice.updated',
                (int) $invoice->id,
            );
        }
    }

    public function deleted(Invoice $invoice): void
    {
        $this->bustCache($invoice);
    }

    private function bustCache(Invoice $invoice): void
    {
        Cache::tags([
            'invoices',
            'dashboard',
            "workspace:{$invoice->workspace_id}",
        ])->flush();
    }
}
