@props(['href' => '#', 'destructive' => false])

<a href="{{ $href }}"
   {{ $attributes->merge(['class' => 'block px-4 py-2 text-sm transition-colors ' . ($destructive ? 'text-red-600 hover:bg-red-50' : 'text-gray-700 hover:bg-gray-50')]) }}>
    {{ $slot }}
</a>
