<?php

namespace App\Livewire\Workspaces;

use App\Models\Workspace;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New Workspace — FreelanceFlow')]
class Create extends Component
{
    #[Rule('required|string|max:255')]
    public string $name = '';

    public function save(): void
    {
        $this->validate();

        $workspace = Workspace::create([
            'name' => $this->name,
            'slug' => Workspace::generateSlug($this->name),
            'owner_id' => auth()->id(),
        ]);

        // Add the creator as owner in the pivot table
        $workspace->users()->attach(auth()->id(), ['role' => 'owner']);

        // Switch to the new workspace immediately
        auth()->user()->switchWorkspace($workspace);

        session()->flash('success', "Workspace \"{$workspace->name}\" created.");

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.workspaces.create');
    }
}