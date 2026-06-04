@props(['variant' => 'primary', 'size' => 'md'])

@php
    $classes = match($variant) {
        'secondary' => 'border-secondary bg-secondary text-secondary-foreground hover:bg-gray-900 active:bg-gray-950',
        'outline'   => 'border-border bg-surface text-secondary hover:border-primary hover:bg-primary-soft hover:text-primary-hover',
        'ghost'     => 'border-transparent bg-transparent text-muted shadow-none hover:bg-surface-muted hover:text-secondary',
        'danger'    => 'border-danger bg-danger text-white hover:bg-red-700 active:bg-red-800',
        default     => 'border-primary bg-primary text-primary-foreground hover:bg-primary-hover active:bg-blue-900',
    };

    $sizes = match($size) {
        'sm' => 'h-9 px-3 text-xs',
        'lg' => 'h-12 px-5 text-base',
        default => 'h-10 px-4 text-sm',
    };
@endphp

<button {{ $attributes->merge(['class' => "marketplace-button {$sizes} {$classes}"]) }}>
    {{ $slot }}
</button>
