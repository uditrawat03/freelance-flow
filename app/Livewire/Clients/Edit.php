<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Client — FreelanceFlow')]
class Edit extends Component
{
    public Client $client;

    #[Rule('required', message: "The name is required")]
    #[Rule('string', message: "The name must be a valid string.")]
    #[Rule('max:255', message: "The name is too long — maximum 255 characters.")]
    public string $name = '';

    #[Rule('required', message: "The email is required")]
    #[Rule('email', message: "The email must be a valid email address.")]
    public string $email = '';

    #[Rule('nullable|string|max:20')]
    public string $phone = '';

    #[Rule('nullable|string|max:255')]
    public string $company = '';

    #[Rule('nullable|string')]
    public string $notes = '';

    #[Rule('required|in:active,inactive,lead')]
    public string $status = 'active';

    // Tracks whether the delete confirmation modal is open
    public bool $confirmingDelete = false;

    public function mount(Client $client): void
    {
        // Route model binding passes the Client instance automatically
        // We fill the component properties from the model
        $this->client  = $client;
        $this->name    = $client->name;
        $this->email   = $client->email;
        $this->phone   = $client->phone ?? '';
        $this->company = $client->company ?? '';
        $this->notes   = $client->notes ?? '';
        $this->status  = $client->status;
    }

    public function update(): void
    {
        // Validate with unique rule that ignores the current client's own email
        $this->validate([
            'email' => "required|email|max:255|unique:clients,email,{$this->client->id}",
        ]);

        $this->client->update([
            'name'    => $this->name,
            'email'   => $this->email,
            'phone'   => $this->phone,
            'company' => $this->company,
            'notes'   => $this->notes,
            'status'  => $this->status,
        ]);

        session()->flash('success', 'Client updated successfully.');

        $this->redirect(route('clients.index'), navigate: true);
    }

    public function confirmDelete(): void
    {
        $this->resetValidation(); // Clear any validation errors before showing the confirmation
        $this->confirmingDelete = true;
    }

    public function delete(): void
    {
        $this->client->delete(); // soft delete

        session()->flash('success', 'Client removed successfully.');

        $this->redirect(route('clients.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.clients.edit');
    }
}