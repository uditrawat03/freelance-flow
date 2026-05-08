<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class Notification extends Component
{
    public ?string $message = null;
    public string  $type    = 'success';
    public bool    $visible = false;

    // Listen for the 'notify' event dispatched by any Livewire component
    #[On('notify')]
    public function show(string $message, string $type = 'success'): void
    {
        $this->message = $message;
        $this->type    = $type;
        $this->visible = true;

        // Auto-dismiss success and info after 4 seconds
        if (in_array($type, ['success', 'info'])) {
            $this->js('setTimeout(() => $wire.dismiss(), 4000)');
        }
    }

    public function dismiss(): void
    {
        $this->visible  = false;
        $this->message  = null;
    }

    public function render()
    {
        return view('livewire.notification');
    }
}