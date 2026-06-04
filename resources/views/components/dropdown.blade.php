@props(['label' => 'Actions', 'align' => 'right'])

@php
    $alignClass = match($align) {
        'left' => 'left-0',
        'center' => 'left-1/2 -translate-x-1/2',
        default => 'right-0',
    };
@endphp

<div x-data="{ open: false }" @click.away="open = false" class="relative inline-block">
    <button @click="open = ! open"
            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 text-sm font-medium text-gray-700 transition-colors">
        {{ $label }}
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
        </svg>
    </button>

    <div x-show="open"
         x-transition
         class="absolute {{ $alignClass }} mt-2 w-48 rounded-xl shadow-lg border border-gray-100 bg-white z-40">
        <div class="py-1">
            {{ $slot }}
        </div>
    </div>
</div>
