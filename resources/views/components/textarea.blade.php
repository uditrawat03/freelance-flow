@props([
    'label' => '',
    'name' => '',
    'id' => '',
    'placeholder' => '',
    'value' => '',
    'error' => '',
    'hint' => '',
    'rows' => 4,
    'required' => false,
    'disabled' => false,
])

@php
    $textareaId = $id ?: 'textarea_' . uniqid();
    $hasError = $error || $errors->has($name);
@endphp

<div class="space-y-1.5">
    @if($label)
        <label for="{{ $textareaId }}" class="block text-sm font-semibold text-secondary">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    <textarea
        id="{{ $textareaId }}"
        name="{{ $name }}"
        placeholder="{{ $placeholder }}"
        rows="{{ $rows }}"
        @if($disabled) disabled @endif
        @if($required) required @endif
        {{ $attributes->merge(['class' => '
            marketplace-field resize-none
            ' . ($hasError
                ? 'border-danger bg-red-50 text-red-950 placeholder-red-300 focus:border-danger focus:ring-danger/15'
                : ''
            )
        ']) }}>{{ $value ?: old($name) }}</textarea>

    @if($hasError)
        <x-input-error :messages="$errors->get($name)" />
    @endif

    @if($hint && !$hasError)
        <p class="text-xs text-muted">{{ $hint }}</p>
    @endif
</div>
