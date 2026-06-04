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
        <label for="{{ $textareaId }}" class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
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
            w-full px-4 py-2.5 rounded-xl border text-sm font-medium resize-none
            transition-colors duration-200
            focus:outline-none focus:ring-2 focus:ring-indigo-200
            ' . ($hasError
                ? 'border-red-300 bg-red-50 text-red-900 placeholder-red-300'
                : 'border-gray-200 bg-white text-gray-900 placeholder-gray-400 hover:border-gray-300'
            ) . ($disabled ? ' opacity-50 cursor-not-allowed' : '')
        ']) }}>{{ $value ?: old($name) }}</textarea>

    @if($hasError)
        <x-input-error :messages="$errors->get($name)" />
    @endif

    @if($hint && !$hasError)
        <p class="text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
