<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'FreelanceFlow') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/app-inertia.js'])
    @fluxAppearance
    @inertiaHead
</head>
<body class="min-h-screen bg-background font-sans antialiased text-foreground">
    @include('partials.navbar')

    <div class="flex pt-16">
        @auth
            @include('partials.sidebar')
        @endauth

        <main class="min-h-[calc(100vh-64px)] flex-1 bg-background">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <x-flash-message />
                @inertia
            </div>
        </main>
    </div>

    <livewire:notification />

    @stack('scripts')
    @fluxScripts
</body>
</html>
