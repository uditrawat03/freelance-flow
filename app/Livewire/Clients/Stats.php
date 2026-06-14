<?php

namespace App\Livewire\Clients;

use App\Services\ClientService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class Stats extends Component
{
    public function placeholder(): View
    {
        return view('livewire.clients.stats-placeholder');
    }

    public function render(ClientService $clientService): View
    {
        $stats = $clientService->statistics();

        return view('livewire.clients.stats', [
            'stats' => [
                'total' => array_sum($stats),
                'active' => $stats['active'] ?? 0,
                'inactive' => $stats['inactive'] ?? 0,
                'lead' => $stats['lead'] ?? 0,
            ],
        ]);
    }
}
