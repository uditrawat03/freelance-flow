@props([
    'label' => '',
    'name' => '',
    'id' => '',
    'placeholder' => 'Select an option...',
    'value' => '',
    'error' => '',
    'hint' => '',
    'options' => [],
    'required' => false,
    'disabled' => false,
])

@php
    $selectId = $id ?: 'select_' . uniqid();
    $hasError = $error || $errors->has($name);
    $currentValue = $value ?: old($name);
@endphp

<div class="space-y-1.5">
    @if($label)
        <label for="{{ $selectId }}" class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <select
        id="{{ $selectId }}"
        name="{{ $name }}"
        @if($disabled) disabled @endif
        @if($required) required @endif
        {{ $attributes->merge(['class' => '
            w-full px-4 py-2.5 rounded-xl border text-sm font-medium appearance-none
            transition-colors duration-200
            focus:outline-none focus:ring-2 focus:ring-indigo-200
            bg-white bg-no-repeat
            ' . ($hasError
                ? 'border-red-300 bg-red-50 text-red-900'
                : 'border-gray-200 bg-white text-gray-900 hover:border-gray-300'
            ) . ($disabled ? ' opacity-50 cursor-not-allowed' : '')
        ']) }}
        style="background-image: url('data:image/svg+xml;charset=utf-8,<svg xmlns=%22http://www.w3.org/2000/svg%22 fill=%226b7280%22 viewBox=%220 0 20 20%22><path fill-rule=%22evenodd%22 d=%22M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z%22 clip-rule=%22evenodd%22/></svg>'); background-position: right 0.5rem center; background-size: 1.5em 1.5em; padding-right: 2.5rem;"
    >
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach($options as $key => $label)
            <option value="{{ $key }}" {{ $key == $currentValue ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>

    @if($hasError)
        <x-input-error :messages="$errors->get($name)" />
    @endif

    @if($hint && !$hasError)
        <p class="text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
