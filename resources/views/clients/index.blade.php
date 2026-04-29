@extends('layouts.app')

@section('title', 'Clients — FreelanceFlow')

@section('content')
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-semibold">Clients</h1>
        <a href="{{ route('clients.create') }}" class="rounded-lg px-4 py-1 bg-blue-500 hover:bg-blue-600 text-white">
            Add client
        </a>
    </div>

    @forelse ($clients as $client)
        <div
            class="client-card flex items-center justify-between gap-4 p-4 mb-4 bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md transition">
            <div class="flex flex-col">
                <strong class="text-lg font-semibold text-gray-900">
                    {{ $client->name }}
                </strong>

                <span class="text-sm text-gray-500">
                    {{ $client->email }}
                </span>
            </div>

            @php
                $statusClasses = match ($client->status) {
                    'active' => 'bg-green-100 text-green-700',
                    'inactive' => 'bg-red-100 text-red-700',
                    default => 'bg-gray-100 text-gray-700',
                };
            @endphp

            <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full {{ $statusClasses }}">
                {{ ucfirst($client->status) }}
            </span>
        </div>


    @empty
        <p>No clients yet.
            <a href="{{ route('clients.create') }}">Add your first one.</a>
        </p>
    @endforelse
@endsection