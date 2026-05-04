<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class EmptyState extends Component
{
    public function __construct(
        public string $message = 'Nothing here yet.',
        public string $ctaText = '',
        public string $ctaHref = '',
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.empty-state');
    }
}