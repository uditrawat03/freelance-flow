@props(['align' => 'left'])

@php
    $alignClass = match($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };
@endphp

<td {{ $attributes->merge(['class' => "px-6 py-4 text-sm text-secondary $alignClass"]) }}>
    {{ $slot }}
</td>
