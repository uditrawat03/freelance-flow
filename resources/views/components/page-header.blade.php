@props(['title' => '', 'subtitle' => ''])

<header class="mb-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">{{ $title }}</h1>
            @if($subtitle)
                <p class="text-sm text-gray-500 mt-1">{{ $subtitle }}</p>
            @endif
        </div>
        <div class="flex items-center gap-2">
            {{ $actions ?? '' }}
            @if ($slot->isNotEmpty())
                <div class="shrink-0">
                    {{ $slot }}
                </div>
            @endif
        </div>
    </div>
</header>