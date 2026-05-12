<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'notes' => $this->notes,

            // Conditionally include project count if loaded
            'projects_count' => $this->whenCounted('projects'),

            // Conditionally include projects if loaded
            'projects' => ProjectResource::collection(
                $this->whenLoaded('projects')
            ),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}