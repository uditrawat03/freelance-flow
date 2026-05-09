@extends('layouts.app')

@section('title', $client->display_name . ' — FreelanceFlow')

@section('content')

    <x-page-header :title="$client->display_name" :subtitle="$client->email">
        <div class="flex items-center gap-3">
            <x-client-status :status="$client->status" />
            <a href="{{ route('clients.edit', $client) }}"
                class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                Edit client
            </a>
        </div>
    </x-page-header>

    {{-- Client meta --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        @if ($client->company)
            <div class="bg-white border border-gray-200 rounded-lg px-4 py-3">
                <p class="text-xs text-gray-400">Company</p>
                <p class="text-sm font-medium text-gray-900 mt-0.5">{{ $client->company }}</p>
            </div>
        @endif
        @if ($client->phone)
            <div class="bg-white border border-gray-200 rounded-lg px-4 py-3">
                <p class="text-xs text-gray-400">Phone</p>
                <p class="text-sm font-medium text-gray-900 mt-0.5">{{ $client->phone }}</p>
            </div>
        @endif
        <div class="bg-white border border-gray-200 rounded-lg px-4 py-3">
            <p class="text-xs text-gray-400">Projects</p>
            <p class="text-sm font-medium text-gray-900 mt-0.5">{{ $client->projects->count() }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg px-4 py-3">
            <p class="text-xs text-gray-400">Client since</p>
            <p class="text-sm font-medium text-gray-900 mt-0.5">
                {{ $client->created_at->format('M Y') }}
            </p>
        </div>
    </div>

    {{-- Projects section --}}
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900">Projects</h2>
        <a href="{{ route('projects.create', ['client' => $client->id]) }}"
            class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-md transition-colors">
            + New project
        </a>
    </div>

    @forelse ($client->projects()->latest()->get() as $project)
        <div class="bg-white border border-gray-200 rounded-lg px-5 py-4 mb-2 flex items-center justify-between">
            <div>
                <p class="font-medium text-gray-900 text-sm">{{ $project->name }}</p>
                <div class="flex items-center gap-3 mt-1">
                    <x-project-status :status="$project->status" />
                    @if ($project->deadline)
                        <span class="text-xs {{ $project->is_overdue ? 'text-red-500 font-medium' : 'text-gray-400' }}">
                            {{ $project->is_overdue ? 'Overdue · ' : 'Due ' }}
                            {{ $project->deadline->format('M d, Y') }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-4">
                @if ($project->budget)
                    <span class="text-sm text-gray-600 font-medium">{{ $project->formatted_budget }}</span>
                @endif
                <a href="{{ route('projects.edit', $project) }}" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Edit
                </a>
            </div>
        </div>
    @empty
        <x-empty-state message="No projects for this client yet." cta-text="Add first project"
            :cta-href="route('projects.create', ['client' => $client->id])" />
    @endforelse

@endsection