<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->with(['client', 'tags'])
            ->when($request->status, fn($q) => $q->status($request->status))
            ->when($request->client_id, fn($q) => $q->where('client_id', $request->client_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ProjectResource::collection($projects);
    }

    public function store(Request $request): ProjectResource
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:draft,active,on_hold,completed,cancelled',
            'budget' => 'nullable|numeric|min:0',
            'deadline' => 'nullable|date',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        $project = Project::create($validated);

        if (!empty($validated['tag_ids'])) {
            $project->tags()->sync($validated['tag_ids']);
        }

        return new ProjectResource($project->load(['client', 'tags']));
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($project->load(['client', 'tags']));
    }

    public function update(Request $request, Project $project): ProjectResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:draft,active,on_hold,completed,cancelled',
            'budget' => 'nullable|numeric|min:0',
            'deadline' => 'nullable|date',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        $project->update($validated);

        if (array_key_exists('tag_ids', $validated)) {
            $project->tags()->sync($validated['tag_ids'] ?? []);
        }

        return new ProjectResource($project->fresh()->load(['client', 'tags']));
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json(['message' => 'Project deleted successfully.']);
    }
}