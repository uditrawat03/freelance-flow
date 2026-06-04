@props([
    'count' => 3,
    'height' => 'h-4',
    'width' => 'w-full',
])

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @for($i = 0; $i < $count; $i++)
        <div class="bg-gray-200 rounded-lg animate-pulse {{ $height }} {{ $width }}"></div>
    @endfor
</div>
