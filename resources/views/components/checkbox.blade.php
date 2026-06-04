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
    $checkboxId = $id ?: 'checkbox_' . uniqid();
    $hasError = $error || $errors->has($name);
    $isChecked = $checked || old($name) == $value;
@endphp

<div class="flex items-start gap-3">
    <input
        type="checkbox"
        id="{{ $checkboxId }}"
        name="{{ $name }}"
        value="{{ $value }}"
        @if($isChecked) checked @endif
        @if($disabled) disabled @endif
        {{ $attributes->merge(['class' => '
            w-5 h-5 rounded-lg border-2 border-gray-200 text-indigo-600
            transition-colors duration-200
            focus:outline-none focus:ring-2 focus:ring-indigo-200 focus:ring-offset-2
            cursor-pointer
            ' . ($hasError ? 'border-red-300' : '')
            . ($disabled ? ' opacity-50 cursor-not-allowed' : '')
        ]) }}
    />

    <div class="flex-1">
        @if($label)
            <label for="{{ $checkboxId }}" class="text-sm font-medium text-gray-700 cursor-pointer">
                {{ $label }}
            </label>
        @endif

        @if($hasError)
            <x-input-error :messages="$errors->get($name)" />
        @endif
    </div>
</div>
