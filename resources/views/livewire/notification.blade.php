@php
    $styles = [
        'success' => 'bg-green-50 border-green-200 text-green-800',
        'error' => 'bg-red-50 border-red-200 text-red-800',
        'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-800',
        'info' => 'bg-blue-50 border-blue-200 text-blue-800',
    ];
    $style = $styles[$type] ?? $styles['info'];
@endphp

<div>
    @if ($visible && $message)
        <div class="fixed bottom-4 right-4 z-50 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-md max-w-sm {{ $style }}"
            wire:key="notification-{{ $type }}">
            <span class="flex-1">{{ $message }}</span>
            <button wire:click="dismiss" class="shrink-0 opacity-60 hover:opacity-100 transition-opacity"
                aria-label="Dismiss">
                &times;
            </button>
        </div>
    @endif
</div>