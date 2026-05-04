<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class FormCard extends Component
{
    public function __construct(public string $maxWidth = 'max-w-2xl') {}

    public function render(): View|Closure|string
    {
        return view('components.form-card');
    }
}