@props(['variant' => 'primary'])

@php
    $classes = match($variant) {
        'secondary' => 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50',
        'ghost'     => 'bg-transparent text-gray-600 hover:bg-gray-50',
        default     => 'bg-indigo-600 text-white hover:bg-indigo-700',
    };
@endphp

<button {{ $attributes->merge(['class' => "inline-flex items-center gap-2 px-4 py-2 rounded-xl font-medium transition " . $classes]) }}>
    {{ $slot }}
</button>
