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
        <div class="client-card">
            <strong>{{ $client['name'] }}</strong>
            <span>{{ $client['email'] }}</span>
        </div>
    @empty
        <p>No clients yet.
            <a href="{{ route('clients.create') }}">Add your first one.</a>
        </p>
    @endforelse
@endsection