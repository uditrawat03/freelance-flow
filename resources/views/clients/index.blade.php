@extends('layouts.app')

@section('title', 'Clients — FreelanceFlow')

@section('content')

    {{-- After: clean, readable, maintainable --}}
    <x-page-header title="Clients" subtitle="Manage all your clients.">
        <a href="{{ route('clients.create') }}" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-md transition-colors">
            + Add client
        </a>
    </x-page-header>

    {{-- Client list --}}
    @forelse ($clients as $client)
        <div class="bg-white border border-gray-200 rounded-lg px-5 py-4 mb-3 flex items-center justify-between">

            {{-- Client info --}}
            <div>
                <p class="font-medium text-gray-900">{{ $client->name }}</p>
                <p class="text-sm text-gray-500">{{ $client->email }}</p>
                @if ($client->company)
                    <p class="text-xs text-gray-400 mt-0.5">{{ $client->company }}</p>
                @endif
            </div>

            {{-- Right side: badge + actions --}}
            <div class="flex items-center gap-4">

                {{-- Status badge --}}
                <x-client-status :status="$client->status" />

                {{-- Action buttons --}}
                <div class="flex items-center gap-2">
                    <a
                        href="{{ route('clients.edit', $client) }}"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        Edit
                    </a>
                </div>

            </div>

        </div>
    @empty
        <x-empty-state
            message="No clients yet."
            cta-text="Add your first client"
            :cta-href="route('clients.create')"
        />
    @endforelse

@endsection