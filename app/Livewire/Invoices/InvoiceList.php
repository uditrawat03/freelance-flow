<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Invoices — FreelanceFlow')]
class InvoiceList extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $status = '';

    public bool $confirmingDelete = false;
    public ?int $deletingInvoiceId = null;
    public bool $confirmingGenerate = false;
    public ?int $generatingInvoiceId = null;

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    // --- Generate PDF action ---
    public function confirmGenerate(int $invoiceId): void
    {
        $this->generatingInvoiceId = $invoiceId;
        $this->confirmingGenerate = true;
    }

    public function generatePdf(InvoiceService $invoiceService): void
    {
        $invoice = Invoice::findOrFail($this->generatingInvoiceId);

        $invoiceService->generatePdf($invoice);

        $this->confirmingGenerate = false;
        $this->generatingInvoiceId = null;

        session()->flash('success', "PDF generated for invoice {$invoice->number}.");
    }

    // --- Mark as sent ---
    public function markSent(int $invoiceId): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->markAsSent();

        session()->flash('success', "Invoice {$invoice->number} marked as sent.");
    }

    // --- Mark as paid ---
    public function markPaid(int $invoiceId): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->markAsPaid();

        session()->flash('success', "Invoice {$invoice->number} marked as paid.");
    }

    // --- Delete ---
    public function confirmDelete(int $invoiceId): void
    {
        $this->deletingInvoiceId = $invoiceId;
        $this->confirmingDelete = true;
    }

    public function delete(): void
    {
        abort_unless(auth()->user()->can('delete invoices'), 403);
        $invoice = Invoice::findOrFail($this->deletingInvoiceId);
        $invoice->delete();

        $this->confirmingDelete = false;
        $this->deletingInvoiceId = null;

        session()->flash('success', 'Invoice deleted.');
    }

    public function render()
    {
        $invoices = Invoice::query()
            ->with('client')
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(15);

        return view('livewire.invoices.invoice-list', compact('invoices'));
    }
}