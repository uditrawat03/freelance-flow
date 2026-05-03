<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Add Client — FreelanceFlow')]
class Create extends Component
{
    #[Rule(
        rule: 'required|string|min:2|max:255',
        messages: [
            'required' => 'The client name cannot be empty.',
            'min'      => 'The name must be at least 2 characters.',
        ]
    )]
    public string $name = '';

    #[Rule(
        rule: 'required|email|max:255|unique:clients,email',
        messages: [
            'required' => 'An email address is required.',
            'email'    => 'That does not look like a valid email address.',
            'unique'   => 'A client with this email already exists in FreelanceFlow.',
        ]
    )]
    public string $email = '';

    #[Rule('nullable|string|max:20')]
    public string $phone = '';

    #[Rule('nullable|string|max:255')]
    public string $company = '';

    #[Rule('nullable|string')]
    public string $notes = '';

    #[Rule(
        rule: 'required|in:active,inactive,lead',
        messages: ['required' => 'Please select a client status.']
    )]
    public string $status = 'active';

    // Per-field real-time validation
    public function updatedName(): void
    {
        $this->validateOnly('name');
    }

    public function updatedEmail(): void
    {
        $this->validateOnly('email');
    }

    public function save(): void
    {
        $this->validate();

        // Cross-field: if status is 'lead', phone is required
        if ($this->status === 'lead' && empty($this->phone)) {
            $this->addError('phone', 'A phone number is required for leads.');
            return;
        }

        Client::create([
            'name'    => $this->name,
            'email'   => $this->email,
            'phone'   => $this->phone,
            'company' => $this->company,
            'notes'   => $this->notes,
            'status'  => $this->status,
        ]);

        session()->flash('success', 'Client added successfully.');

        $this->redirect(route('clients.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.clients.create');
    }
}