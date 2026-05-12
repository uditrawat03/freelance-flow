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