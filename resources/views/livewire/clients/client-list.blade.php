<div>
    <livewire:clients.stats />

    {{-- Page header --}}
    <x-page-header title="Clients" subtitle="Manage and track all your client relationships.">
        <a href="{{ route('clients.create') }}" wire:navigate
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-xl transition-colors">
            + New client
        </a>
    </x-page-header>

    {{-- Search and filter bar --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-4 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            {{-- Live search input --}}
            <div class="relative flex-1">
                <input wire:model.live.debounce.300ms="search" type="text"
                    dusk="client-search"
                    placeholder="Search by name, email or company..."
                    class="w-full text-sm border border-gray-200 rounded-xl pl-10 pr-10 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-200 focus:border-indigo-300" />
                {{-- Search icon --}}
                <svg class="absolute left-3 top-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                </svg>
                {{-- Clear search button --}}
                @if ($search)
                    <button wire:click="clearSearch" dusk="clear-client-search" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 transition-colors"
                        aria-label="Clear search">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>

            {{-- Status filter pills --}}
            <div class="flex items-center gap-2 shrink-0 flex-wrap">
                @foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'lead' => 'Leads'] as $value => $label)
                    <button wire:click="setStatus('{{ $value }}')"
                        dusk="client-status-{{ $value ?: 'all' }}"
                        class="text-sm px-3 py-2 rounded-lg font-medium transition-colors whitespace-nowrap
                            {{ $status === $value
                            ? 'bg-indigo-600 text-white'
                            : 'text-gray-600 bg-gray-100 hover:bg-gray-200' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Result count --}}
        <p class="text-xs text-gray-500 mt-3">
            <span class="font-semibold text-gray-700">{{ $clients->total() }}</span> {{ Str::plural('client', $clients->total()) }}
            @if ($search)
                matching <span class="font-medium text-indigo-600">"{{ $search }}"</span>
            @endif
            @if ($status)
                · <span class="font-medium text-indigo-600">{{ ucfirst($status) }}</span> only
            @endif
        </p>
    </div>

    {{-- Loading indicator --}}
    <div wire:loading.delay.long wire:target="search,status,setStatus,clearSearch" class="text-center py-4">
        <div class="inline-flex items-center gap-2 text-sm text-gray-500">
            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Searching...
        </div>
    </div>

    {{-- Client list --}}
    <div wire:loading.class="opacity-50" wire:target="search,status,setStatus,clearSearch" class="space-y-3">

        @forelse ($clients as $client)
            <x-card class="flex items-center justify-between hover:shadow-md transition-shadow group">
                {{-- Avatar + info --}}
                <div class="flex items-center gap-4 flex-1">
                    {{-- Initials avatar --}}
                    <div class="w-11 h-11 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0 group-hover:bg-indigo-200 transition-colors">
                        <span class="text-sm font-bold text-indigo-700">{{ $client->initials }}</span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <a href="{{ route('clients.show', $client) }}" wire:navigate
                            class="font-semibold text-gray-900 text-sm hover:text-indigo-600 transition-colors block">
                            {{ $client->display_name }}
                        </a>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $client->email }}</p>
                        <div class="flex items-center gap-2 mt-1 text-xs text-gray-400">
                            <span>{{ $client->projects_count }} {{ Str::plural('project', $client->projects_count) }}</span>
                            <span>·</span>
                            <span>Added {{ $client->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>

                {{-- Right: status + actions --}}
                <div class="flex items-center gap-4 flex-shrink-0 ml-4">
                    <x-client-status :status="$client->status" />
                    @can('update', $client)
                        <a href="{{ route('clients.edit', $client) }}" wire:navigate class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-indigo-600 hover:bg-indigo-50 transition-colors">
                            Edit
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.658 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                        </a>
                    @endcan
                </div>
            </x-card>
        @empty
            <x-empty-state 
                message="{{ $search || $status ? 'No clients match your search.' : 'No clients yet.' }}"
                cta-text="{{ !$search && !$status ? 'Add your first client' : '' }}"
                :cta-href="!$search && !$status ? route('clients.create') : ''" />
        @endforelse

    </div>

    {{-- Pagination --}}
    @if ($clients->hasPages())
        <div class="mt-4">
            {{ $clients->links() }}
        </div>
    @endif

</div>
