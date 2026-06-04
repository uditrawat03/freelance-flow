@props([
    'label' => '',
    'name' => '',
    'id' => '',
    'value' => '',
    'error' => '',
    'checked' => false,
    'disabled' => false,
])

@php
    $radioId = $id ?: 'radio_' . uniqid();
    $hasError = $error || $errors->has($name);
    $isChecked = $checked || old($name) == $value;
@endphp

<div class="flex items-center gap-3">
    <input
        type="radio"
        id="{{ $radioId }}"
        name="{{ $name }}"
        value="{{ $value }}"
        @if($isChecked) checked @endif
        @if($disabled) disabled @endif
        {{ $attributes->merge(['class' => '
            w-5 h-5 rounded-full border-2 border-border text-primary
            transition-colors duration-200
            focus:outline-none focus:ring-4 focus:ring-primary/15 focus:ring-offset-2
            cursor-pointer
            ' . ($hasError ? 'border-danger' : '')
            . ($disabled ? ' opacity-50 cursor-not-allowed' : '')
        ]) }}
    />

    @if($label)
        <label for="{{ $radioId }}" class="cursor-pointer text-sm font-semibold text-secondary">
            {{ $label }}
        </label>
    @endif
</div>

@if($hasError)
    <x-input-error :messages="$errors->get($name)" />
@endif
