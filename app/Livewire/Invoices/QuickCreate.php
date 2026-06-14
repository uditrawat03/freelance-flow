<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use App\Services\ClientService;
use App\Services\InvoiceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class QuickCreate extends Component
{
    public bool $open = false;

    public ?int $client_id = null;

    public string $description = '';

    public ?float $amount = null;

    public function openModal(): void
    {
        Gate::authorize('create', Invoice::class);

        $this->reset(['client_id', 'description', 'amount']);
        $this->resetValidation();
        $this->open = true;
    }

    public function save(InvoiceService $invoiceService): void
    {
        Gate::authorize('create', Invoice::class);

        $this->validate([
            'client_id' => [
                'required',
                Rule::exists('clients', 'id')->where('workspace_id', auth()->user()->currentWorkspace()?->id),
            ],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
        ]);

        $invoice = $invoiceService->create([
            'client_id' => $this->client_id,
            'tax_rate' => config('freelanceflow.invoice.default_tax_rate', 18.0),
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(config('freelanceflow.invoice.default_due_days', 30))->toDateString(),
            'line_items' => [
                [
                    'description' => $this->description,
                    'quantity' => 1,
                    'rate' => $this->amount,
                ],
            ],
            'status' => 'draft',
        ]);

        $this->open = false;
        $this->reset(['client_id', 'description', 'amount']);

        $this->dispatch('invoice-created');
        $this->dispatch(
            'notify',
            message: __('app.invoices.saved', ['number' => $invoice->number]),
            type: 'success',
        );
    }

    public function render(ClientService $clientService): View
    {
        return view('livewire.invoices.quick-create', [
            'clients' => $clientService->activeClients(),
        ]);
    }
}
