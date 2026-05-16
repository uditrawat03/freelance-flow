<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {
    }

    /**
     * Download the invoice as a PDF.
     */
    public function download(Invoice $invoice): Response
    {
        abort_if(!auth()->check(), 403);

        $content = $this->invoiceService->getPdfContent($invoice);
        $filename = "{$invoice->number}.pdf";

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Preview the invoice PDF inline in the browser.
     */
    public function preview(Invoice $invoice): Response
    {
        abort_if(!auth()->check(), 403);

        $content = $this->invoiceService->getPdfContent($invoice);
        $filename = "{$invoice->number}.pdf";

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Mark the invoice as sent and generate the PDF.
     */
    public function send(Invoice $invoice): \Illuminate\Http\RedirectResponse
    {
        $this->invoiceService->generatePdf($invoice);
        $invoice->markAsSent();

        return redirect()->back()->with('success', 'Invoice marked as sent.');
    }

    /**
     * Mark the invoice as paid.
     */
    public function markPaid(Invoice $invoice): \Illuminate\Http\RedirectResponse
    {
        $invoice->markAsPaid();

        return redirect()->back()->with('success', 'Invoice marked as paid.');
    }

    public function destroy(Invoice $invoice)
    {
        abort_unless(auth()->user()->can('delete invoices'), 403);
        $invoice->delete();
    }
}