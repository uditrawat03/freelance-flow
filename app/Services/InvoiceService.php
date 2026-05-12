<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /**
     * Generate the PDF and store it on disk.
     * Returns the stored file path.
     */
    public function generatePdf(Invoice $invoice): string
    {
        // Eager-load everything the template needs
        $invoice->loadMissing(['client', 'project']);

        // Render the Blade template to a PDF binary
        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
            ]);

        // Build the filename: INV-2026-001.pdf
        $filename = "{$invoice->number}.pdf";
        $path = "invoices/{$filename}";

        // Store on the local private disk
        Storage::disk('local')->put($path, $pdf->output());

        // Save the path on the invoice record
        $invoice->update(['pdf_path' => $path]);

        return $path;
    }

    /**
     * Get the PDF content for streaming/download.
     */
    public function getPdfContent(Invoice $invoice): string
    {
        if (!$invoice->has_pdf) {
            $this->generatePdf($invoice);
        }

        return Storage::disk('local')->get($invoice->pdf_path);
    }

    /**
     * Delete the stored PDF (e.g. when invoice is edited).
     */
    public function deletePdf(Invoice $invoice): void
    {
        if ($invoice->pdf_path) {
            Storage::disk('local')->delete($invoice->pdf_path);
            $invoice->update(['pdf_path' => null]);
        }
    }

    /**
     * Create a new invoice with auto-generated number.
     */
    public function create(array $data): Invoice
    {
        $data['number'] = Invoice::generateNumber();

        $invoice = Invoice::create($data);

        $invoice->recalculate();

        return $invoice;
    }
}