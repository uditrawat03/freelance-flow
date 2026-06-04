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
            w-5 h-5 rounded-full border-2 border-gray-200 text-indigo-600
            transition-colors duration-200
            focus:outline-none focus:ring-2 focus:ring-indigo-200 focus:ring-offset-2
            cursor-pointer
            ' . ($hasError ? 'border-red-300' : '')
            . ($disabled ? ' opacity-50 cursor-not-allowed' : '')
        ]) }}
    />

    @if($label)
        <label for="{{ $radioId }}" class="text-sm font-medium text-gray-700 cursor-pointer">
            {{ $label }}
        </label>
    @endif
</div>

@if($hasError)
    <x-input-error :messages="$errors->get($name)" />
@endif
