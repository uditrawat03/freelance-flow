<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ClientStatus extends Component
{
    public string $badgeClass;

    public function __construct(public string $status)
    {
        $this->badgeClass = match($status) {
            'active'   => 'bg-green-100 text-green-700',
            'inactive' => 'bg-gray-100 text-gray-600',
            'lead'     => 'bg-yellow-100 text-yellow-700',
            default    => 'bg-gray-100 text-gray-600',
        };
    }

    public function render(): View|Closure|string
    {
        return view('components.client-status');
    }
}