<?php

namespace App\Livewire;

use App\Models\Workspace;
use Livewire\Component;

class WorkspaceSwitcher extends Component
{
    public bool $open = false;

    public function switch(int $workspaceId): void
    {
        $workspace = Workspace::findOrFail($workspaceId);

        // Security: only switch if user has access
        abort_unless(auth()->user()->hasWorkspaceAccess($workspace), 403);

        auth()->user()->switchWorkspace($workspace);

        // Redirect to dashboard to reload with new workspace context
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        $currentWorkspace = auth()->user()->currentWorkspace();
        $workspaces = auth()->user()->workspaces()->get();

        return view('livewire.workspace-switcher', compact('currentWorkspace', 'workspaces'));
    }
}