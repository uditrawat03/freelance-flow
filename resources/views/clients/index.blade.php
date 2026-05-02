@extends('layouts.app')

@section('title', 'Clients — FreelanceFlow')

@section('content')

    {{-- Flash message --}}
    @if (session('success'))
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
            </svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">Clients</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $clients->count() }} {{ Str::plural('client', $clients->count()) }}</p>
        </div>
        <a
            href="{{ route('clients.create') }}"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-md transition-colors"
        >
            + Add client
        </a>
    </div>

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
                @php
                    $badgeClass = match($client->status) {
                        'active'   => 'bg-green-100 text-green-700',
                        'inactive' => 'bg-gray-100 text-gray-600',
                        'lead'     => 'bg-yellow-100 text-yellow-700',
                        default    => 'bg-gray-100 text-gray-600',
                    };
                @endphp
                <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $badgeClass }}">
                    {{ ucfirst($client->status) }}
                </span>

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
        <div class="text-center py-16">
            <p class="text-gray-400 text-sm">No clients yet.</p>
            <a href="{{ route('clients.create') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">
                Add your first client
            </a>
        </div>
    @endforelse

@endsection