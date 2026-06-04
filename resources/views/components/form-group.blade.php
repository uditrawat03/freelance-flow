@props([
    'label' => '',
    'cols' => 1,
])

<div class="space-y-2">
    @if($label)
        <label class="block text-sm font-medium text-gray-700">{{ $label }}</label>
    @endif
    <div class="grid gap-4" style="grid-template-columns: repeat({{ $cols }}, 1fr);">
        {{ $slot }}
    </div>
</div>
