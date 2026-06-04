@props([
    'striped' => true,
    'hover' => true,
])

<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-lg border border-border bg-surface shadow-soft']) }}>
    <table class="w-full text-sm">
        {{ $slot }}
    </table>
</div>
