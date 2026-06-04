<div class="text-center py-16">
    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary-soft">
        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4" />
        </svg>
    </div>
    <p class="text-sm text-muted">{{ $message }}</p>
    @if ($ctaText && $ctaHref)
        <a href="{{ $ctaHref }}" class="mt-3 inline-block text-sm font-semibold text-primary hover:text-primary-hover">
            {{ $ctaText }}
        </a>
    @endif
</div>
