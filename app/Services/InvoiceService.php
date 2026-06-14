<?php

namespace App\Services;

use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
    ) {}

    public function create(array $data): Invoice
    {
        $invoice = $this->invoices->create($data);
        $this->bustCache();

        return $invoice;
    }

    public function list(string $status = '', int $perPage = 15): LengthAwarePaginator
    {
        return $this->invoices->paginate($status, $perPage);
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $updated = $this->invoices->update($invoice, $data);
        $this->bustCache();

        return $updated;
    }

    public function delete(Invoice $invoice): void
    {
        $this->deletePdf($invoice);
        $this->invoices->delete($invoice);
        $this->bustCache();
    }

    public function generatePdf(Invoice $invoice): string
    {
        $invoice->loadMissing(['client', 'project']);

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
            ]);

        $path = "invoices/{$invoice->number}.pdf";

        Storage::disk('local')->put($path, $pdf->output());

        $invoice->update(['pdf_path' => $path]);

        return $path;
    }

    public function getPdfContent(Invoice $invoice): string
    {
        if (! $invoice->has_pdf) {
            $this->generatePdf($invoice);
        }

        return Storage::disk('local')->get($invoice->pdf_path);
    }

    public function deletePdf(Invoice $invoice): void
    {
        if ($invoice->pdf_path) {
            Storage::disk('local')->delete($invoice->pdf_path);
            $invoice->update(['pdf_path' => null]);
        }
    }

    public function totalRevenue(): float
    {
        return $this->invoices->totalRevenue();
    }

    public function revenueByMonth(int $months = 12): array
    {
        return $this->invoices->revenueByMonth($months);
    }

    public function overdueInvoices(): Collection
    {
        return $this->invoices->overdueInvoices();
    }

    private function workspaceId(): ?int
    {
        return auth()->user()->currentWorkspace()?->id;
    }

    public function bustCache(): void
    {
        Cache::tags([
            'invoices',
            'dashboard',
            "workspace:{$this->workspaceId()}",
        ])->flush();
    }
}
