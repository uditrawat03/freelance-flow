<?php

namespace App\Livewire\Projects;

use App\Models\Attachment;
use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use App\Notifications\ProjectStatusChanged;
use App\Services\ProjectService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Edit Project — FreelanceFlow')]
class Edit extends Component
{
    use WithFileUploads;

    public Project $project;

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

    #[Rule('nullable|date')]
    public ?string $deadline = null;

    #[Rule('nullable|array')]
    public array $selectedTags = [];

    // File upload property — can be a single file or array for multiple
    #[Rule('nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,zip')]
    public $newFile = null;

    public bool $confirmingDelete  = false;
    public ?int $deletingAttachmentId = null;

    public function mount(Project $project): void
    {
        $this->project           = $project;
        $this->selectedClientId  = $project->client_id;
        $this->name              = $project->name;
        $this->description       = $project->description ?? '';
        $this->status            = $project->status;
        $this->budget            = $project->budget;
        $this->deadline          = $project->deadline?->format('Y-m-d');
        $this->selectedTags      = $project->tags->pluck('id')->toArray();
    }

    public function updatedNewFile(): void
    {
        $this->validateOnly('newFile');
    }

    public function uploadFile(): void
    {
        $this->validateOnly('newFile');

        if (! $this->newFile) {
            return;
        }

        $originalName = $this->newFile->getClientOriginalName();
        $mimeType     = $this->newFile->getMimeType();
        $size         = $this->newFile->getSize();

        // Store the file in storage/app/private/attachments/
        $storedName = $this->newFile->store('attachments', 'local');

        // Save metadata to the database
        $this->project->attachments()->create([
            'original_name' => $originalName,
            'stored_name'   => $storedName,
            'mime_type'     => $mimeType,
            'size'          => $size,
            'disk'          => 'local',
        ]);

        // Reset the file input
        $this->newFile = null;
        $this->reset('newFile');

        // Refresh the project to show the new attachment
        $this->project->refresh();

        session()->flash('success', 'File uploaded successfully.');
    }

    public function confirmDeleteAttachment(int $attachmentId): void
    {
        $this->deletingAttachmentId = $attachmentId;
        $this->confirmingDelete     = true;
    }

    public function deleteAttachment(): void
    {
        $attachment = Attachment::findOrFail($this->deletingAttachmentId);

        // Ensure this attachment belongs to this project
        abort_if($attachment->project_id !== $this->project->id, 403);

        // Delete file from disk first
        $attachment->deleteFromStorage();

        // Then delete the database record
        $attachment->delete();

        $this->confirmingDelete       = false;
        $this->deletingAttachmentId   = null;
        $this->project->refresh();

        session()->flash('success', 'File removed.');
    }

    public function update(ProjectService $projectService): void
    {
        $this->validate();

        // Capture the status before saving
        $previousStatus = $this->project->status;

        $projectService->update($this->project, [
            'client_id'   => $this->selectedClientId,
            'name'        => $this->name,
            'description' => $this->description,
            'status'      => $this->status,
            'budget'      => $this->budget ?: null,
            'deadline'    => $this->deadline ?: null,
        ]);

        $this->project->tags()->sync($this->selectedTags);

         // Only notify if the status actually changed
        if ($previousStatus !== $this->status) {
            $this->project->loadMissing('client');
            Log::info("Project status changed for Project ID {$this->project->id}: '{$previousStatus}' → '{$this->status}'");
            // Notify the currently logged-in user
            // In Phase 4 we extend this to notify the client too
            auth()->user()->notify(
                new ProjectStatusChanged($this->project, $previousStatus)
            );
        }

        session()->flash('success', 'Project updated successfully.');

        $this->redirect(
            route('clients.show', $this->project->client_id),
            navigate: true
        );
    }

    public function render()
    {
        return view('livewire.projects.edit', [
            'clients'     => Client::active()->orderBy('name')->get(),
            'tags'        => Tag::orderBy('name')->get(),
            'attachments' => $this->project->attachments()->latest()->get(),
        ]);
    }
}
