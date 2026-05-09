@props(['maxWidth' => 'max-w-2xl'])

<flux:card class="{{ $maxWidth }} p-6 space-y-5">
    {{ $slot }}
</flux:card>
