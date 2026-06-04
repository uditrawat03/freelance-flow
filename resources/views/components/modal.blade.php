@props([
    'id' => 'modal_' . uniqid(),
    'title' => '',
    'size' => 'md',
])

@php
    $sizes = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
    ];
@endphp

<div x-data="{ open: false }"
     x-show="open"
     x-transition
     @open-modal-{{ $id }}.window="open = true"
     @close-modal-{{ $id }}.window="open = false"
     @keydown.escape.window="open = false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="display: none;">

    <!-- Backdrop -->
    <div x-show="open"
         @click="open = false"
         x-transition
         class="fixed inset-0 bg-black/50"></div>

    <!-- Modal -->
    <div x-show="open"
         x-transition
         class="relative w-full {{ $sizes[$size] }} rounded-lg border border-border bg-surface p-6 shadow-lifted">

        {{-- Header --}}
        @if($title)
            <div class="mb-4 flex items-center justify-between border-b border-border pb-4">
                <h2 class="text-lg font-semibold text-foreground">{{ $title }}</h2>
                <button @click="open = false" class="text-muted hover:text-secondary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif

        {{-- Content --}}
        <div>
            {{ $slot }}
        </div>

        {{-- Footer actions --}}
        @if(isset($footer))
            <div class="mt-6 flex justify-end gap-3 border-t border-border pt-4">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
