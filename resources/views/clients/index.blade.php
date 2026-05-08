@extends('layouts.app')

@section('title', 'Clients — FreelanceFlow')

@section('content')

    <x-page-header title="Clients" subtitle="Manage all your clients in one place.">
        <a
            href="{{ route('clients.create') }}"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-md transition-colors"
        >
            + Add client
        </a>
    </x-page-header>

    {{-- Livewire component owns search, filters, and the list --}}
    <livewire:clients.client-list />

@endsection