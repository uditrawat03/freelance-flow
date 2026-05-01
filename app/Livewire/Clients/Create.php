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
    #[Rule('required|string|max:255')]
    public string $name = '';

    #[Rule('required|email|max:255|unique:clients,email')]
    public string $email = '';

    #[Rule('nullable|string|max:20')]
    public string $phone = '';

    #[Rule('nullable|string|max:255')]
    public string $company = '';

    #[Rule('nullable|string')]
    public string $notes = '';

    #[Rule('required|in:active,inactive,lead')]
    public string $status = 'active';

    public function save(): void
    {
        $this->validate();

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