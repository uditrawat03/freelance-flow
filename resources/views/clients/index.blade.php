@extends('layouts.app')

@section('title', 'Clients — FreelanceFlow')

@section('content')

    {{-- After: clean, readable, maintainable --}}
    <x-page-header title="Clients" subtitle="Manage all your clients.">
        <a href="{{ route('clients.create') }}"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-md transition-colors">
            + Add client
        </a>
    </x-page-header>

    <div class="flex items-center gap-3 mb-5">

        {{-- Status filters --}}
        <div class="flex items-center gap-2">
            @foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'lead' => 'Leads'] as $value => $label)
                <a href="{{ route('clients.index', array_filter(['status' => $value === 'all' ? null : $value, 'search' => $search])) }}"
                    class="text-sm px-3 py-1.5 rounded-full border transition-colors
                            {{ ($status ?? 'all') === $value
                ? 'bg-indigo-600 text-white border-indigo-600'
                : 'text-gray-600 border-gray-200 hover:border-indigo-300' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- Search --}}
        <form method="GET" action="{{ route('clients.index') }}" class="flex items-center gap-2 ml-auto">
            @if ($status)
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <input type="text" name="search" value="{{ $search }}" placeholder="Search clients..."
                class="text-sm border border-gray-200 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-300 w-48">
            <button type="submit" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                Search
            </button>
            @if ($search)
                <a href="{{ route('clients.index', ['status' => $status]) }}" class="text-sm text-gray-400 hover:text-gray-600">
                    Clear
                </a>
            @endif
        </form>

    </div>

    {{-- Client list --}}
    @forelse ($clients as $client)
        <div class="bg-white border border-gray-200 rounded-lg px-5 py-4 mb-3 flex items-center justify-between">
            
            {{-- Client info --}}
            <div class="flex items-center gap-3">
                {{-- Avatar with initials --}}
                <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center">
                    <span class="text-xs font-semibold text-indigo-700">{{ $client->initials }}</span>
                </div> 
                <div>
                    <p class="font-medium text-gray-900">
                        {{ $client->name }}
                    </p>
                    <p class="text-sm text-gray-500">{{ $client->email }}</p>
                    @if ($client->company)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $client->company }}</p>
                    @endif
                    <p class="text-xs text-gray-400 mt-0.5">
                        Added {{ $client->created_at->diffForHumans() }}
                    </p>
                </div>
            </div>

            {{-- Right side: badge + actions --}}
            <div class="flex items-center gap-4">

                {{-- Status badge --}}
                <x-client-status :status="$client->status" />

                {{-- Action buttons --}}
                <div class="flex items-center gap-2">
                    <a href="{{ route('clients.edit', $client) }}"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        Edit
                    </a>
                </div>

            </div>

        </div>
    @empty
        <x-empty-state message="No clients yet." cta-text="Add your first client" :cta-href="route('clients.create')" />
    @endforelse

@endsection