@props([
    'type' => 'text',
    'label' => '',
    'name' => '',
    'id' => '',
    'placeholder' => '',
    'value' => '',
    'error' => '',
    'hint' => '',
    'required' => false,
    'disabled' => false,
])

@php
    $inputId = $id ?: 'input_' . uniqid();
    $hasError = $error || $errors->has($name);
@endphp

<div class="space-y-1.5">
    @if($label)
        <label for="{{ $inputId }}" class="block text-sm font-semibold text-secondary">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    <input
        type="{{ $type }}"
        id="{{ $inputId }}"
        name="{{ $name }}"
        placeholder="{{ $placeholder }}"
        value="{{ $value ?: old($name) }}"
        @if($disabled) disabled @endif
        @if($required) required @endif
        {{ $attributes->merge(['class' => '
            marketplace-field
            ' . ($hasError
                ? 'border-danger bg-red-50 text-red-950 placeholder-red-300 focus:border-danger focus:ring-danger/15'
                : ''
            )
        ']) }}
    />

    @if($hasError)
        <x-input-error :messages="$errors->get($name)" />
    @endif

    @if($hint && !$hasError)
        <p class="text-xs text-muted">{{ $hint }}</p>
    @endif
</div>
