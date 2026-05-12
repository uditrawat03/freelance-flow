<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientCollection;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * GET /api/v1/clients
     * List all clients with optional filtering and pagination.
     */
    public function index(Request $request): ClientCollection
    {
        $clients = Client::query()
            ->withCount('projects')
            ->when($request->status, fn ($q) => $q->status($request->status))
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%")
                      ->orWhere('company', 'like', "%{$request->search}%");
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return new ClientCollection($clients);
    }

    /**
     * POST /api/v1/clients
     * Create a new client.
     */
    public function store(Request $request): ClientResource
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|unique:clients,email',
            'phone'   => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'notes'   => 'nullable|string',
            'status'  => 'nullable|in:active,inactive,lead',
        ]);

        $client = Client::create($validated);

        return new ClientResource($client);
    }

    /**
     * GET /api/v1/clients/{client}
     * Show a single client with their projects.
     */
    public function show(Client $client): ClientResource
    {
        $client->load(['projects' => fn ($q) => $q->with('tags')->latest()]);

        return new ClientResource($client);
    }

    /**
     * PUT /api/v1/clients/{client}
     * Update a client.
     */
    public function update(Request $request, Client $client): ClientResource
    {
        $validated = $request->validate([
            'name'    => 'sometimes|required|string|max:255',
            'email'   => "sometimes|required|email|unique:clients,email,{$client->id}",
            'phone'   => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'notes'   => 'nullable|string',
            'status'  => 'nullable|in:active,inactive,lead',
        ]);

        $client->update($validated);

        return new ClientResource($client->fresh());
    }

    /**
     * DELETE /api/v1/clients/{client}
     * Soft delete a client.
     */
    public function destroy(Client $client): JsonResponse
    {
        $client->delete();

        return response()->json([
            'message' => 'Client deleted successfully.',
        ], 200);
    }
}