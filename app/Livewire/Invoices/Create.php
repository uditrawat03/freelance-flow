<?php

namespace App\Livewire\Invoices;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\InvoiceService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New Invoice — FreelanceFlow')]
class Create extends Component
{
    #[Rule('required|exists:clients,id')]
    public ?int $client_id = null;

    #[Rule('nullable|exists:projects,id')]
    public ?int $project_id = null;

    #[Rule('nullable|string')]
    public string $notes = '';

    #[Rule('required|numeric|min:0|max:100')]
    public float $tax_rate = 18.0;

    #[Rule('nullable|date')]
    public ?string $issued_at = null;

    #[Rule('nullable|date|after_or_equal:issued_at')]
    public ?string $due_at = null;

    // Line items — each row: description, quantity, rate
    public array $lineItems = [
        ['description' => '', 'quantity' => 1, 'rate' => 0],
    ];

    public function addLineItem(): void
    {
        $this->lineItems[] = ['description' => '', 'quantity' => 1, 'rate' => 0];
    }

    public function removeLineItem(int $index): void
    {
        if (count($this->lineItems) > 1) {
            array_splice($this->lineItems, $index, 1);
        }
    }

    public function getSubtotalProperty(): float
    {
        return collect($this->lineItems)
            ->sum(fn($item) => ($item['quantity'] ?? 0) * ($item['rate'] ?? 0));
    }

    public function getTaxAmountProperty(): float
    {
        return $this->subtotal * ($this->tax_rate / 100);
    }

    public function getTotalProperty(): float
    {
        return $this->subtotal + $this->tax_amount;
    }

    // Projects filtered by selected client
    public function getProjectsProperty()
    {
        return $this->client_id
            ? Project::where('client_id', $this->client_id)->get()
            : collect();
    }

    public function save(InvoiceService $invoiceService): void
    {
        $this->validate();

        // Validate line items manually
        foreach ($this->lineItems as $index => $item) {
            if (empty($item['description'])) {
                $this->addError("lineItems.{$index}.description", 'Description is required.');
                return;
            }
            if (($item['quantity'] ?? 0) <= 0) {
                $this->addError("lineItems.{$index}.quantity", 'Quantity must be greater than 0.');
                return;
            }
            if (($item['rate'] ?? 0) < 0) {
                $this->addError("lineItems.{$index}.rate", 'Rate cannot be negative.');
                return;
            }
        }

        $invoice = $invoiceService->create([
            'client_id' => $this->client_id,
            'project_id' => $this->project_id ?: null,
            'notes' => $this->notes,
            'tax_rate' => $this->tax_rate,
            'issued_at' => $this->issued_at ?: now()->toDateString(),
            'due_at' => $this->due_at,
            'line_items' => $this->lineItems,
            'status' => 'draft',
        ]);

        session()->flash('success', "Invoice {$invoice->number} created.");

        $this->redirect(route('invoices.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.invoices.create', [
            'clients' => Client::active()->orderBy('name')->get(),
        ]);
    }
}