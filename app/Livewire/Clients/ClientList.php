<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ClientList extends Component
{
    use WithPagination;

    // #[Url] syncs the property to the URL query string automatically
    // The browser URL updates as the user searches or filters
    // The state survives page refresh and is shareable
    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    // Reset pagination to page 1 whenever search changes
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // Reset pagination to page 1 whenever status filter changes
    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        // Toggle off if same status clicked again
        $this->status = $this->status === $status ? '' : $status;
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function render()
    {
        $clients = Client::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%")
                      ->orWhere('company', 'like', "%{$this->search}%");
                });
            })
            ->when($this->status, fn ($query) => $query->status($this->status))
            ->latest()
            ->paginate(10);

        return view('livewire.clients.client-list', [
            'clients' => $clients,
        ]);
    }
}