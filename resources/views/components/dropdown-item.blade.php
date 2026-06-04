@props(['href' => '#', 'destructive' => false])

<a href="{{ $href }}"
   {{ $attributes->merge(['class' => 'block px-4 py-2 text-sm font-medium transition-colors ' . ($destructive ? 'text-danger hover:bg-red-50' : 'text-secondary hover:bg-surface-muted')]) }}>
    {{ $slot }}
</a>
