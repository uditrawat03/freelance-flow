<?php

namespace App\Livewire\Projects;

use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;


#[Layout('layouts.app')]
#[Title('New Project — FreelanceFlow')]
class Create extends Component
{
    use WithFileUploads;

    #[Url(as: 'client')]
    public ?int $client_id = null;

    #[Rule('required|exists:clients,id')]
    public ?int $selectedClientId = null;

    #[Rule('required|string|max:255')]
    public string $name = '';

    #[Rule('nullable|string')]
    public string $description = '';

    #[Rule('required|in:draft,active,on_hold,completed,cancelled')]
    public string $status = 'draft';

    #[Rule('nullable|numeric|min:0')]
    public ?string $budget = null;

    #[Rule('nullable|date|after_or_equal:today')]
    public ?string $deadline = null;

    #[Rule('nullable|array')]
    public array $selectedTags = [];

    public function mount(): void
    {
        if ($this->client_id) {
            $this->selectedClientId = $this->client_id;
        }
    }

    public function save(): void
    {
        $this->validate();

        $project = Project::create([
            'client_id'   => $this->selectedClientId,
            'name'        => $this->name,
            'description' => $this->description,
            'status'      => $this->status,
            'budget'      => $this->budget ?: null,
            'deadline'    => $this->deadline ?: null,
        ]);

        $project->tags()->sync($this->selectedTags);

        session()->flash('success', 'Project created. Client will be notified shortly.');

        $this->redirect(
            route('clients.show', $this->selectedClientId),
            navigate: true
        );
    }

    public function render()
    {
        return view('livewire.projects.create', [
            'clients' => Client::active()->orderBy('name')->get(),
            'tags'    => Tag::orderBy('name')->get(),
        ]);
    }
}
