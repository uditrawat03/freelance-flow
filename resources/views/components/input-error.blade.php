@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li class="text-xs font-medium text-red-600">{{ $message }}</li>
        @endforeach
    </ul>
@endif
