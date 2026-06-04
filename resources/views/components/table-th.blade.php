@props(['sortable' => false])

<th {{ $attributes->merge(['class' => 'px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wide ' . ($sortable ? 'cursor-pointer hover:bg-surface-muted' : '')]) }}>
    {{ $slot }}
</th>
