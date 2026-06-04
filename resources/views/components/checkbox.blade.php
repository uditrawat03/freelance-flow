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
            w-5 h-5 rounded border-2 border-border text-primary
            transition-colors duration-200
            focus:outline-none focus:ring-4 focus:ring-primary/15 focus:ring-offset-2
            cursor-pointer
            ' . ($hasError ? 'border-danger' : '')
            . ($disabled ? ' opacity-50 cursor-not-allowed' : '')
        ]) }}
    />

    <div class="flex-1">
        @if($label)
            <label for="{{ $checkboxId }}" class="cursor-pointer text-sm font-semibold text-secondary">
                {{ $label }}
            </label>
        @endif

        @if($hasError)
            <x-input-error :messages="$errors->get($name)" />
        @endif
    </div>
</div>
