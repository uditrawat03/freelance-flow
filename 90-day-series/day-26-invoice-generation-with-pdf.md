# Day 26 — Invoice Generation with PDF

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 17 min · **Level:** Intermediate

---

> *"A freelancer without invoices does not get paid. Today FreelanceFlow gets its most business-critical feature — professional PDF invoices generated from a Blade template, stored on disk, downloadable by the client, and tracked through a full status lifecycle. Draft, sent, paid, overdue — the complete invoicing flow."*

---

## What We Are Building Today

1. The **invoices table** — schema for a complete invoice system
2. The **Invoice model** with relationships, scopes, and accessors
3. **Install DomPDF** — server-side PDF generation
4. A **Blade PDF template** — professional invoice design
5. The **InvoiceService** — generates, stores, and retrieves the PDF
6. A **download endpoint** — streams the PDF to the browser
7. **Invoice status lifecycle** — draft → sent → paid → overdue
8. The **invoices Livewire component** — list and manage invoices

---

## Step 1 — The Invoices Migration

```bash
php artisan make:migration create_invoices_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('project_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            // Human-readable invoice number: INV-2026-001
            $table->string('number')->unique();

            $table->string('status')->default('draft');
            // draft | sent | paid | overdue | cancelled

            $table->text('notes')->nullable();

            // Line items stored as JSON array
            // [{"description": "Web Design", "quantity": 1, "rate": 50000, "amount": 50000}]
            $table->json('line_items');

            // Calculated totals stored separately for performance
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);   // percentage e.g. 18.00
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->date('paid_at')->nullable();

            // Path to the generated PDF on disk
            $table->string('pdf_path')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
```

```bash
php artisan migrate
```

---

## Step 2 — The Invoice Model

```bash
php artisan make:model Invoice
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'project_id',
        'number',
        'status',
        'notes',
        'line_items',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'issued_at',
        'due_at',
        'paid_at',
        'pdf_path',
    ];

    protected $casts = [
        'line_items'  => 'array',    // JSON column auto-decoded
        'subtotal'    => 'decimal:2',
        'tax_rate'    => 'decimal:2',
        'tax_amount'  => 'decimal:2',
        'total'       => 'decimal:2',
        'issued_at'   => 'date',
        'due_at'      => 'date',
        'paid_at'     => 'date',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'deleted_at'  => 'datetime',
    ];

    // --- Relationships ---

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // --- Scopes ---

    public function scopeDraft(Builder $query): void
    {
        $query->where('status', 'draft');
    }

    public function scopeSent(Builder $query): void
    {
        $query->where('status', 'sent');
    }

    public function scopePaid(Builder $query): void
    {
        $query->where('status', 'paid');
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', 'sent')
              ->whereNotNull('due_at')
              ->where('due_at', '<', now());
    }

    public function scopeUnpaid(Builder $query): void
    {
        $query->whereIn('status', ['sent', 'draft']);
    }

    // --- Accessors ---

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match($this->status) {
                'draft'     => 'Draft',
                'sent'      => 'Sent',
                'paid'      => 'Paid',
                'overdue'   => 'Overdue',
                'cancelled' => 'Cancelled',
                default     => ucfirst($this->status),
            }
        );
    }

    protected function formattedSubtotal(): Attribute
    {
        return Attribute::make(
            get: fn () => '₹' . number_format($this->subtotal, 2)
        );
    }

    protected function formattedTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => '₹' . number_format($this->total, 2)
        );
    }

    protected function formattedTaxAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => '₹' . number_format($this->tax_amount, 2)
        );
    }

    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'sent'
                && $this->due_at
                && $this->due_at->isPast()
        );
    }

    protected function hasPdf(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->pdf_path
                && Storage::disk('local')->exists($this->pdf_path)
        );
    }

    // --- Business logic ---

    // Auto-generate invoice number: INV-2026-001
    public static function generateNumber(): string
    {
        $year    = now()->year;
        $count   = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('INV-%d-%03d', $year, $count);
    }

    // Recalculate totals from line items
    public function recalculate(): void
    {
        $subtotal = collect($this->line_items)
            ->sum(fn ($item) => $item['quantity'] * $item['rate']);

        $taxAmount = $subtotal * ($this->tax_rate / 100);

        $this->update([
            'subtotal'   => $subtotal,
            'tax_amount' => $taxAmount,
            'total'      => $subtotal + $taxAmount,
        ]);
    }

    // Mark as sent
    public function markAsSent(): void
    {
        $this->update([
            'status'    => 'sent',
            'issued_at' => $this->issued_at ?? now(),
        ]);
    }

    // Mark as paid
    public function markAsPaid(): void
    {
        $this->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);
    }
}
```

---

## Step 3 — Add the Relationship to Client and Project Models

```php
// app/Models/Client.php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function invoices(): HasMany
{
    return $this->hasMany(Invoice::class);
}

// app/Models/Project.php
public function invoices(): HasMany
{
    return $this->hasMany(Invoice::class);
}
```

---

## Step 4 — Install DomPDF

DomPDF is a pure-PHP HTML-to-PDF renderer. It reads a Blade view and converts it to a PDF binary.

```bash
composer require barryvdh/laravel-dompdf
```

Publish the config:

```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

This creates `config/dompdf.php`. For FreelanceFlow, update a few settings:

```php
// config/dompdf.php
'options' => [
    'font_dir'            => storage_path('fonts/'),
    'font_cache'          => storage_path('fonts/'),
    'chroot'              => realpath(base_path()),
    'log_output_file'     => null,
    'enable_font_subsetting' => false,
    'pdf_backend'         => 'CPDF',
    'default_media_type'  => 'screen',
    'default_paper_size'  => 'a4',
    'default_font'        => 'sans-serif',
    'dpi'                 => 96,
    'enable_php'          => false,  // security: never enable PHP in PDFs
    'enable_javascript'   => false,
    'enable_remote'       => false,  // do not load external resources
    'unicode_enabled'     => true,
    'font_height_ratio'   => 1.1,
    'enable_html5_parser' => true,
],
```

---

## Step 5 — Build the PDF Blade Template

Create the invoice template at `resources/views/pdf/invoice.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; font-size: 13px; color: #1f2937; line-height: 1.5; }
        .container { padding: 40px 48px; }

        /* Header */
        .header { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .brand { font-size: 24px; font-weight: 700; color: #6366f1; }
        .invoice-meta { text-align: right; }
        .invoice-number { font-size: 20px; font-weight: 600; color: #111827; }
        .invoice-status { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 4px; }
        .status-draft     { background: #f3f4f6; color: #6b7280; }
        .status-sent      { background: #eff6ff; color: #1d4ed8; }
        .status-paid      { background: #f0fdf4; color: #166534; }
        .status-overdue   { background: #fef2f2; color: #991b1b; }

        /* Parties */
        .parties { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .party-label { font-size: 10px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #9ca3af; margin-bottom: 8px; }
        .party-name { font-size: 15px; font-weight: 600; color: #111827; }
        .party-detail { font-size: 12px; color: #6b7280; margin-top: 2px; }

        /* Dates */
        .dates { display: flex; gap: 32px; margin-bottom: 32px; padding: 16px 20px; background: #f9fafb; border-radius: 8px; }
        .date-item { }
        .date-label { font-size: 10px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #9ca3af; }
        .date-value { font-size: 14px; font-weight: 500; color: #111827; margin-top: 2px; }

        /* Line items table */
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        thead th { padding: 10px 12px; text-align: left; font-size: 10px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #9ca3af; border-bottom: 2px solid #e5e7eb; }
        thead th:last-child { text-align: right; }
        tbody td { padding: 14px 12px; border-bottom: 1px solid #f3f4f6; font-size: 13px; color: #374151; vertical-align: top; }
        tbody td:last-child { text-align: right; font-weight: 500; }
        tbody tr:last-child td { border-bottom: none; }

        /* Totals */
        .totals { width: 240px; margin-left: auto; }
        .total-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; color: #6b7280; }
        .total-row.subtotal { border-top: 1px solid #e5e7eb; padding-top: 12px; }
        .total-row.grand { border-top: 2px solid #111827; padding-top: 12px; margin-top: 6px; font-size: 16px; font-weight: 700; color: #111827; }
        .total-label { }
        .total-value { font-weight: 500; }

        /* Notes */
        .notes-section { margin-top: 40px; padding-top: 24px; border-top: 1px solid #e5e7eb; }
        .notes-label { font-size: 10px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #9ca3af; margin-bottom: 8px; }
        .notes-text { font-size: 12px; color: #6b7280; line-height: 1.6; }

        /* Footer */
        .footer { margin-top: 48px; text-align: center; font-size: 11px; color: #d1d5db; }
    </style>
</head>
<body>
<div class="container">

    {{-- Header --}}
    <div class="header">
        <div class="brand">FreelanceFlow</div>
        <div class="invoice-meta">
            <div class="invoice-number">{{ $invoice->number }}</div>
            <div class="invoice-status status-{{ $invoice->status }}">
                {{ $invoice->status_label }}
            </div>
        </div>
    </div>

    {{-- Parties --}}
    <div class="parties">
        <div>
            <div class="party-label">From</div>
            <div class="party-name">{{ config('app.name') }}</div>
            <div class="party-detail">hello@freelanceflow.test</div>
        </div>
        <div style="text-align: right">
            <div class="party-label">Bill To</div>
            <div class="party-name">{{ $invoice->client->name }}</div>
            @if ($invoice->client->company)
                <div class="party-detail">{{ $invoice->client->company }}</div>
            @endif
            <div class="party-detail">{{ $invoice->client->email }}</div>
        </div>
    </div>

    {{-- Dates --}}
    <div class="dates">
        <div class="date-item">
            <div class="date-label">Invoice Date</div>
            <div class="date-value">
                {{ $invoice->issued_at ? $invoice->issued_at->format('M d, Y') : '—' }}
            </div>
        </div>
        <div class="date-item">
            <div class="date-label">Due Date</div>
            <div class="date-value">
                {{ $invoice->due_at ? $invoice->due_at->format('M d, Y') : '—' }}
            </div>
        </div>
        @if ($invoice->project)
            <div class="date-item">
                <div class="date-label">Project</div>
                <div class="date-value">{{ $invoice->project->name }}</div>
            </div>
        @endif
    </div>

    {{-- Line Items --}}
    <table>
        <thead>
            <tr>
                <th style="width: 50%">Description</th>
                <th style="width: 15%; text-align: right">Qty</th>
                <th style="width: 20%; text-align: right">Rate</th>
                <th style="width: 15%; text-align: right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->line_items as $item)
                <tr>
                    <td>{{ $item['description'] }}</td>
                    <td style="text-align: right">{{ $item['quantity'] }}</td>
                    <td style="text-align: right">₹{{ number_format($item['rate'], 2) }}</td>
                    <td>₹{{ number_format($item['quantity'] * $item['rate'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals">
        <div class="total-row subtotal">
            <span class="total-label">Subtotal</span>
            <span class="total-value">{{ $invoice->formatted_subtotal }}</span>
        </div>
        @if ($invoice->tax_rate > 0)
            <div class="total-row">
                <span class="total-label">GST ({{ $invoice->tax_rate }}%)</span>
                <span class="total-value">{{ $invoice->formatted_tax_amount }}</span>
            </div>
        @endif
        <div class="total-row grand">
            <span class="total-label">Total Due</span>
            <span class="total-value">{{ $invoice->formatted_total }}</span>
        </div>
    </div>

    {{-- Notes --}}
    @if ($invoice->notes)
        <div class="notes-section">
            <div class="notes-label">Notes</div>
            <div class="notes-text">{{ $invoice->notes }}</div>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        Thank you for your business · FreelanceFlow · {{ config('app.url') }}
    </div>

</div>
</body>
</html>
```

---

## Step 6 — The InvoiceService

Business logic for PDF generation belongs in a service class — not in a controller or Livewire component.

Create `app/Services/InvoiceService.php`:

```php
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
                      'isRemoteEnabled'      => false,
                  ]);

        // Build the filename: INV-2026-001.pdf
        $filename = "{$invoice->number}.pdf";
        $path     = "invoices/{$filename}";

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
        if (! $invoice->has_pdf) {
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
```

Bind the service in `AppServiceProvider` so it is injectable:

```php
// app/Providers/AppServiceProvider.php
use App\Services\InvoiceService;

public function register(): void
{
    $this->app->singleton(InvoiceService::class);
}
```

---

## Step 7 — The Download Controller

```bash
php artisan make:controller InvoiceController
```

```php
<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * Download the invoice as a PDF.
     */
    public function download(Invoice $invoice): Response
    {
        abort_if(! auth()->check(), 403);

        $content  = $this->invoiceService->getPdfContent($invoice);
        $filename = "{$invoice->number}.pdf";

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Preview the invoice PDF inline in the browser.
     */
    public function preview(Invoice $invoice): Response
    {
        abort_if(! auth()->check(), 403);

        $content  = $this->invoiceService->getPdfContent($invoice);
        $filename = "{$invoice->number}.pdf";

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
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
}
```

Add the routes:

```php
// routes/web.php (inside auth middleware group)
use App\Http\Controllers\InvoiceController;

Route::prefix('invoices')->name('invoices.')->group(function () {
    Route::get('/{invoice}/download', [InvoiceController::class, 'download'])->name('download');
    Route::get('/{invoice}/preview',  [InvoiceController::class, 'preview'])->name('preview');
    Route::post('/{invoice}/send',    [InvoiceController::class, 'send'])->name('send');
    Route::post('/{invoice}/paid',    [InvoiceController::class, 'markPaid'])->name('mark-paid');
});
```

---

## Step 8 — Test Invoice Generation in Tinker

```bash
php artisan tinker

# Create a test invoice
$client = App\Models\Client::first();

$invoice = App\Models\Invoice::create([
    'client_id'  => $client->id,
    'number'     => App\Models\Invoice::generateNumber(),
    'status'     => 'draft',
    'tax_rate'   => 18.00,
    'issued_at'  => now(),
    'due_at'     => now()->addDays(30),
    'line_items' => [
        ['description' => 'Website Design', 'quantity' => 1, 'rate' => 50000],
        ['description' => 'SEO Setup',      'quantity' => 3, 'rate' => 5000],
    ],
    'notes' => 'Payment due within 30 days. UPI: hello@freelanceflow',
]);

$invoice->recalculate();

# Generate the PDF
$service = app(App\Services\InvoiceService::class);
$path    = $service->generatePdf($invoice);

echo "PDF stored at: {$path}";
echo "\nTotal: {$invoice->formatted_total}";

# Verify the file exists
echo "\nExists: " . (Storage::disk('local')->exists($path) ? 'yes' : 'no');
```

Visit `http://localhost:8000/invoices/{invoice-id}/preview` — the PDF opens in the browser.

---

## DomPDF Tips for Clean PDFs

```php
// Paper sizes
->setPaper('a4', 'portrait')
->setPaper('letter', 'landscape')

// Inline CSS works — external stylesheets do not (unless isRemoteEnabled = true)
// Always use inline or <style> tags in PDF templates

// Images must use base64 or absolute paths
<img src="{{ base64_encode(file_get_contents(public_path('logo.png'))) }}" />
// Or:
<img src="{{ public_path('logo.png') }}" />

// Page breaks
<div style="page-break-after: always;"></div>

// Page numbers (DomPDF script — only works with enable_php = true, use carefully)
// Better: just omit page numbers for simple invoices

// Fonts: use web-safe fonts (sans-serif, serif, monospace)
// Custom fonts require installing them in DomPDF's font directory
```

---

## Invoice Status Flow

```
draft → [markAsSent()] → sent → [markAsPaid()] → paid
              ↓
         (if due_at < now)
              ↓
           overdue
```

The `overdue` status is computed from the database, not stored explicitly — a sent invoice is overdue when `due_at < now()`. The scope `Invoice::overdue()` handles this. You can also add a scheduled command to automatically flag overdue invoices:

```php
// app/Console/Commands/FlagOverdueInvoices.php
// Scheduled daily in routes/console.php
Invoice::sent()
    ->whereNotNull('due_at')
    ->where('due_at', '<', now())
    ->update(['status' => 'overdue']);
```

---

## What We Learned Today

- **`json` cast on `line_items`** — the column is stored as JSON in MySQL, auto-decoded to a PHP array on read, auto-encoded on write. No manual `json_encode()` or `json_decode()` needed
- **DomPDF** — `composer require barryvdh/laravel-dompdf`. `Pdf::loadView()->output()` renders a Blade template to PDF binary
- **Private disk for invoices** — PDFs stored in `storage/app/private/invoices/`. Never publicly accessible. Always streamed through the controller with an auth check
- **Service class for PDF logic** — `InvoiceService` owns generate, retrieve, and delete. Controllers and Livewire components call the service, never touch PDF logic directly
- **`Content-Disposition: attachment`** — triggers a file download. `Content-Disposition: inline` renders in the browser PDF viewer
- **`Invoice::generateNumber()`** — counts invoices for the current year, zero-pads to 3 digits: `INV-2026-001`
- **`recalculate()`** — derives subtotal, tax_amount, and total from line_items. Call this whenever line items change
- **`inline` CSS in PDF templates** — DomPDF does not load external stylesheets. All CSS goes in a `<style>` tag in the template

---

## Day 27 — Stripe Payments

Tomorrow FreelanceFlow gets paid. We will integrate Stripe, create a payment intent for an invoice, build a payment page with Stripe Elements, handle the webhook that confirms payment, and automatically mark invoices as paid when the payment succeeds. FreelanceFlow will process its first real payment.

See you on Day 27.