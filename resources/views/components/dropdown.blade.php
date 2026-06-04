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
            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-secondary transition-colors hover:bg-surface-muted">
        {{ $label }}
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
        </svg>
    </button>

    <div x-show="open"
         x-transition
         class="absolute {{ $alignClass }} z-40 mt-2 w-48 rounded-lg border border-border bg-surface shadow-lifted">
        <div class="py-1">
            {{ $slot }}
        </div>
    </div>
</div>
