<div class="text-center py-16">
    <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mb-4">
        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4" />
        </svg>
    </div>
    <p class="text-sm text-gray-500">{{ $message }}</p>
    @if ($ctaText && $ctaHref)
        <a href="{{ $ctaHref }}" class="mt-3 inline-block text-sm text-indigo-600 hover:underline font-medium">
            {{ $ctaText }}
        </a>
    @endif
</div>