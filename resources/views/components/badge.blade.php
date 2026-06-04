@props(['variant' => 'default'])

@php
    $styles = match($variant) {
        'success' => 'bg-green-50 text-green-700 border border-green-200',
        'warning' => 'bg-yellow-50 text-yellow-700 border border-yellow-200',
        'danger'  => 'bg-red-50 text-red-700 border border-red-200',
        'info'    => 'bg-blue-50 text-blue-700 border border-blue-200',
        'default' => 'bg-surface-muted text-secondary border border-border',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold $styles"]) }}>
    {{ $slot }}
</span>
