<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectStatusUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public readonly int $workspaceId,
        public readonly int $projectId,
        public readonly string $projectName,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly string $previousStatus,
        public readonly int|null $clientId,
        public readonly bool $isOverdue,
    ) {
    }

    public static function fromProject(Project $project, string $previousStatus): self
    {
        return new self(
            workspaceId: (int) $project->workspace_id,
            projectId: (int) $project->id,
            projectName: $project->name,
            status: $project->status,
            statusLabel: $project->status_label,
            previousStatus: $previousStatus,
            clientId: $project->client_id ? (int) $project->client_id : null,
            isOverdue: (bool) $project->is_overdue,
        );
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("workspace.{$this->workspaceId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'project.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'project_id' => $this->projectId,
            'project_name' => $this->projectName,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'previous_status' => $this->previousStatus,
            'client_id' => $this->clientId,
            'is_overdue' => $this->isOverdue,
        ];
    }
}
