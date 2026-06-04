@props(['maxWidth' => 'max-w-2xl'])

<flux:card class="{{ $maxWidth }} rounded-lg border border-border bg-surface p-6 shadow-soft space-y-5">
    {{ $slot }}
</flux:card>
