@props([
    'striped' => true,
    'hover' => true,
])

<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-2xl border border-gray-100']) }}>
    <table class="w-full text-sm">
        {{ $slot }}
    </table>
</div>
