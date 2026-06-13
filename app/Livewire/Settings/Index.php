<?php

namespace App\Livewire\Settings;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Settings - FreelanceFlow')]
class Index extends Component
{
    public function render()
    {
        return view('livewire.settings.index');
    }
}
