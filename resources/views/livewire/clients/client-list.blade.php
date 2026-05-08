<div>

    {{-- Search and filter bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-5">

        {{-- Live search input --}}
        <div class="relative flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by name, email or company..."
                class="w-full text-sm border border-gray-200 rounded-lg pl-9 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-300" />
            {{-- Search icon --}}
            <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
            </svg>
            {{-- Clear search button --}}
            @if ($search)
                <button wire:click="clearSearch" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600"
                    aria-label="Clear search">
                    &times;
                </button>
            @endif
        </div>

        {{-- Status filter pills --}}
        <div class="flex items-center gap-2 shrink-0">
            @foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'lead' => 'Leads'] as $value => $label)
                    <button wire:click="setStatus('{{ $value }}')" class="text-sm px-3 py-1.5 rounded-full border transition-colors
                                {{ $status === $value
                ? 'bg-indigo-600 text-white border-indigo-600'
                : 'text-gray-600 border-gray-200 hover:border-indigo-300 bg-white' }}">
                        {{ $label }}
                    </button>
            @endforeach
        </div>

    </div>

    {{-- Result count --}}
    <p class="text-xs text-gray-400 mb-3">
        {{ $clients->total() }} {{ Str::plural('client', $clients->total()) }}
        @if ($search)
            matching <span class="font-medium text-gray-600">"{{ $search }}"</span>
        @endif
        @if ($status)
            · <span class="font-medium text-gray-600">{{ ucfirst($status) }}</span> only
        @endif
    </p>

    {{-- Loading indicator --}}
    <div wire:loading.delay class="text-xs text-gray-400 mb-2">
        Searching...
    </div>

    {{-- Client list --}}
    <div wire:loading.class="opacity-50">

        @forelse ($clients as $client)
            <div
                class="bg-white border border-gray-200 rounded-lg px-5 py-4 mb-2 flex items-center justify-between transition-opacity">

                {{-- Avatar + info --}}
                <div class="flex items-center gap-3">
                    {{-- Initials avatar --}}
                    <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                        <span class="text-xs font-semibold text-indigo-700">
                            {{ $client->initials }}
                        </span>
                    </div>

                    <div>
                        <p class="font-medium text-gray-900 text-sm">{{ $client->display_name }}</p>
                        <p class="text-xs text-gray-500">{{ $client->email }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Added {{ $client->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>

                {{-- Right: badge + edit --}}
                <div class="flex items-center gap-4">
                    <x-client-status :status="$client->status" />
                    <a href="{{ route('clients.edit', $client) }}"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        Edit
                    </a>
                </div>

            </div>
        @empty
            <x-empty-state message="{{ $search || $status ? 'No clients match your search.' : 'No clients yet.' }}"
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