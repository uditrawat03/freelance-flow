<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'budget' => $this->budget,
            'formatted_budget' => $this->formatted_budget,
            'deadline' => $this->deadline?->toDateString(),
            'is_overdue' => $this->is_overdue,

            // Include client only if loaded
            'client' => new ClientResource($this->whenLoaded('client')),

            // Include tags only if loaded
            'tags' => TagResource::collection($this->whenLoaded('tags')),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}