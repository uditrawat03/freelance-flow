@props(['title' => '', 'subtitle' => ''])

<header class="mb-6 border-b border-border pb-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-foreground">{{ $title }}</h1>
            @if($subtitle)
                <p class="text-sm text-muted mt-1">{{ $subtitle }}</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions ?? '' }}
            @if ($slot->isNotEmpty())
                <div class="shrink-0">
                    {{ $slot }}
                </div>
            @endif
        </div>
    </div>
</header>
