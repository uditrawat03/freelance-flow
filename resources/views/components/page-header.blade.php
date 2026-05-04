<div class="flex items-start justify-between mb-6">

    {{-- Left: title and subtitle --}}
    <div>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>

    {{-- Right: optional action button slot --}}
    @if ($slot->isNotEmpty())
        <div class="shrink-0">
            {{ $slot }}
        </div>
    @endif

</div>