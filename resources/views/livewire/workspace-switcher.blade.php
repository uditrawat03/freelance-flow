<div class="relative">
    <button wire:click="$toggle('open')"
        class="flex items-center gap-2 text-sm text-gray-700 hover:text-gray-100 font-medium">
        <span
            class="w-6 h-6 rounded-md bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700">
            {{ substr($currentWorkspace?->name ?? 'W', 0, 1) }}
        </span>
        <span class="max-w-28 truncate">{{ $currentWorkspace?->name ?? 'No workspace' }}</span>
        <svg class="w-3.5 h-3.5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd"
                d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                clip-rule="evenodd" />
        </svg>
    </button>

    @if ($open)
        <div class="absolute left-0 mt-2 w-52 bg-white rounded-xl shadow-lg border border-gray-200 z-50 py-1"
            wire:click.outside="$set('open', false)">
            <p class="px-3 py-1.5 text-xs font-medium text-gray-400 uppercase tracking-wide">
                Your workspaces
            </p>

            @foreach ($workspaces as $workspace)
                <button wire:click="switch({{ $workspace->id }})"
                    class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700
                                   hover:bg-gray-50 transition-colors text-left
                                   {{ $currentWorkspace?->id === $workspace->id ? 'bg-indigo-50 text-indigo-700 font-medium' : '' }}">
                    <span
                        class="w-6 h-6 rounded-md bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700 flex-shrink-0">
                        {{ substr($workspace->name, 0, 1) }}
                    </span>
                    <span class="truncate">{{ $workspace->name }}</span>
                    @if ($currentWorkspace?->id === $workspace->id)
                        <svg class="w-3.5 h-3.5 ml-auto shrink-0 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                clip-rule="evenodd" />
                        </svg>
                    @endif
                </button>
            @endforeach

            <div class="border-t border-gray-100 mt-1 pt-1">
                <a href="{{ route('workspaces.create') }}"
                    class="flex items-center gap-2 px-3 py-2 text-sm text-gray-500 hover:bg-gray-50 transition-colors">
                    <span
                        class="w-6 h-6 rounded-md border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-400 text-base leading-none">+</span>
                    New workspace
                </a>
            </div>
        </div>
    @endif
</div>