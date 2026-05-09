<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ProjectStatus extends Component
{
    public string $badgeClass;
    public string $label;

    public function __construct(public string $status)
    {
        [$this->badgeClass, $this->label] = match($status) {
            'draft'     => ['bg-gray-100 text-gray-600',   'Draft'],
            'active'    => ['bg-blue-100 text-blue-700',   'Active'],
            'on_hold'   => ['bg-yellow-100 text-yellow-700', 'On Hold'],
            'completed' => ['bg-green-100 text-green-700', 'Completed'],
            'cancelled' => ['bg-red-100 text-red-600',     'Cancelled'],
            default     => ['bg-gray-100 text-gray-600',   ucfirst($status)],
        };
    }

    public function render(): View|Closure|string
    {
        return view('components.project-status');
    }
}